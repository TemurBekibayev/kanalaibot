<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PaymentConfirmation;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\RedisStateManager;
use App\Services\Payment\ManualPaymentProvider;
use App\Jobs\GenerateAiPostJob;
use App\Jobs\PublishPostJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class TelegramWebhookController extends Controller
{
    protected TelegramBotService $telegram;
    protected RedisStateManager $stateManager;

    public function __construct(TelegramBotService $telegram, RedisStateManager $stateManager)
    {
        $this->telegram = $telegram;
        $this->stateManager = $stateManager;
    }

    public function handle(Request $request)
    {
        $update = $request->all();

        if (empty($update)) {
            return response()->json(['status' => 'empty update'], 200);
        }

        try {
            // 1. Handle Pre Checkout Query (Telegram Stars Payment Authorization)
            if (isset($update['pre_checkout_query'])) {
                return $this->handlePreCheckoutQuery($update['pre_checkout_query']);
            }

            // 2. Handle Callback Query (Inline Keyboard Buttons)
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }

            // 3. Handle Messages (Commands, text, screenshots, payments)
            if (isset($update['message'])) {
                return $this->handleMessage($update['message']);
            }

        } catch (Exception $e) {
            Log::channel('telegram_errors')->error("Webhook handling error: " . $e->getMessage(), [
                'exception' => $e,
                'update' => $update
            ]);
        }

        return response()->json(['status' => 'processed'], 200);
    }

    /**
     * Handle Telegram Stars Pre-Checkout Verification.
     */
    protected function handlePreCheckoutQuery(array $query)
    {
        $queryId = $query['id'];
        // Pre-checkout always passes verification
        $this->telegram->answerPreCheckoutQuery($queryId, true);
        return response()->json(['status' => 'answered_pre_checkout'], 200);
    }

    /**
     * Handle incoming chat message.
     */
    protected function handleMessage(array $message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? $message['caption'] ?? '';
        $fromId = $message['from']['id'] ?? $chatId;
        $username = $message['from']['username'] ?? null;
        $name = $message['from']['first_name'] ?? 'User';

        // Get or Create Bot User
        $user = User::firstOrCreate(
            ['telegram_id' => $fromId],
            [
                'username' => $username,
                'name' => $name,
                'plan' => 'free',
                'daily_limit' => 5,
                'daily_used' => 0,
            ]
        );

        // Handle successful payment receipt (from Telegram Stars)
        if (isset($message['successful_payment'])) {
            return $this->handleSuccessfulPayment($user, $message['successful_payment']);
        }

        // Handle commands
        if (str_starts_with($text, '/')) {
            return $this->handleCommand($user, $text);
        }

        // Fetch User State from Redis
        $session = $this->stateManager->getState($user->telegram_id);
        $step = $session['step'] ?? 'idle';

        // Check if user is uploading a payment screenshot
        if (isset($message['photo']) && $step === 'awaiting_payment_proof') {
            return $this->handlePaymentProofUpload($user, $message['photo']);
        }

        // Check if user is adding a channel (by username or ID)
        if ($step === 'awaiting_channel_id') {
            return $this->handleChannelConnection($user, $text);
        }

        // Check if user is typing custom scheduling date/time
        if ($step === 'awaiting_schedule_time') {
            return $this->handleCustomSchedulingTime($user, $session['data']['post_id'] ?? null, $text);
        }

        // Check if user is typing missing info for a template
        if ($step === 'awaiting_missing_info') {
            return $this->handleProvidedMissingInfo($user, $session['data']['post_id'] ?? null, $text);
        }

        // Extract media attachments if any
        $mediaType = 'none';
        $mediaUrl = null;

        if (isset($message['photo']) && $step !== 'awaiting_payment_proof') {
            $largestPhoto = end($message['photo']);
            $fileId = $largestPhoto['file_id'];
            $mediaUrl = $this->telegram->getFileUrl($fileId);
            if ($mediaUrl) {
                $mediaType = 'photo';
            }
        } elseif (isset($message['video'])) {
            $fileId = $message['video']['file_id'];
            $mediaUrl = $this->telegram->getFileUrl($fileId);
            if ($mediaUrl) {
                $mediaType = 'video';
            }
        }

        // Default flow: treat text as prompt for AI post generation
        if (!empty($text)) {
            return $this->handlePostPromptInput($user, $text, $mediaType, $mediaUrl);
        }

        return response()->json(['status' => 'ignored_message_type'], 200);
    }

    /**
     * Handle Bot Commands (/start, /help, /stats, /pay, /mychannels)
     */
    protected function handleCommand(User $user, string $text)
    {
        $command = explode(' ', $text)[0];

        switch ($command) {
            case '/start':
                $this->stateManager->clearState($user->telegram_id);
                $welcome = "👋 **Assalomu alaykum, {$user->name}!**\n\n" .
                           "Men — kanalingiz boshqaruvini osonlashtiradigan **AI Kanal Manager** botiman.\n\n" .
                           "🤖 **Asosiy imkoniyatlarim:**\n" .
                           "• Qisqa matndan chiroyli post yaratish (Gemini/Groq AI)\n" .
                           "• Postlarni navbatga qo'yish (Rejalashtirish)\n" .
                           "• Dublikatlarni tekshirish va eski postlarni avtomatik o'chirish\n" .
                           "• Mini App orqali batafsil tahrirlash va kalendar ko'rinishidagi postlar jadvali\n\n" .
                           "🚀 **Boshlash uchun:**\n" .
                           "1️⃣ Meni kanalingizga **Administrator** (Post joylash huquqi bilan) qilib qo'shing.\n" .
                           "2️⃣ Kanalni ulash uchun /mychannels buyrug'ini bosing va kanalingiz havolasini yuboring.";

                $this->telegram->sendMessage($user->telegram_id, $welcome, [
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '📢 Kanallarni boshqarish', 'callback_data' => 'menu:channels']],
                            [['text' => '💎 Tariflar', 'callback_data' => 'menu:plans']],
                        ]
                    ])
                ]);
                break;

            case '/help':
                $helpText = "❓ **Yordam bo'limi:**\n\n" .
                            "/start - Botni qayta ishga tushirish\n" .
                            "/mychannels - Kanallarni ulash va sozlash\n" .
                            "/pay - Premium/Biznes tarifga o'tish (to'lov)\n" .
                            "/stats - Foydalanish statistikangiz\n\n" .
                            "✍️ **Post yaratish uchun:** Shunchaki xohlagan matningizni botga yozib yuboring (masalan: `Cobalt sotiladi 2023 yil, 13000$, kraskasi toza, 45000 km yurgan`). Men chiroyli formatlangan post tayyorlab beraman!";
                $this->telegram->sendMessage($user->telegram_id, $helpText);
                break;

            case '/stats':
                $stats = "📊 **Foydalanish statistikasi:**\n\n" .
                         "👤 Foydalanuvchi: *{$user->name}*\n" .
                         "⭐ Tarif: *" . ucfirst($user->plan) . "*\n" .
                         "📅 Kunlik AI limit: *" . ($user->plan === 'free' ? '5 ta' : 'Cheksiz') . "*\n" .
                         "✅ Bugun ishlatilgan: *{$user->daily_used} ta*\n" .
                         "📢 Ulangan kanallar: *" . $user->channels()->count() . " ta*\n\n" .
                         "🔗 Batafsil ma'lumot olish uchun Mini App hisobot panelini oching.";
                
                // Add Mini App WebApp Button if Premium
                $buttons = [];
                $miniAppUrl = env('APP_URL') . "/mini-app/stats?tg_id=" . $user->telegram_id;
                $buttons[] = [['text' => '📊 Grafik ko\'rinishida ko\'rish', 'web_app' => ['url' => $miniAppUrl]]];
                
                $this->telegram->sendMessage($user->telegram_id, $stats, [
                    'reply_markup' => json_encode(['inline_keyboard' => $buttons])
                ]);
                break;

            case '/mychannels':
                $this->showChannelsMenu($user);
                break;

            case '/pay':
                $this->showPlansMenu($user);
                break;

            default:
                $this->telegram->sendMessage($user->telegram_id, "❓ Noma'lum buyruq. Barcha buyruqlarni ko'rish uchun /help bosing.");
        }

        return response()->json(['status' => 'command_handled'], 200);
    }

    /**
     * Handle incoming callback query buttons.
     */
    protected function handleCallbackQuery(array $callbackQuery)
    {
        $callbackId = $callbackQuery['id'];
        $fromId = $callbackQuery['from']['id'];
        $message = $callbackQuery['message'];
        $messageId = $message['message_id'];
        $data = $callbackQuery['data'];

        $user = User::where('telegram_id', $fromId)->first();
        if (!$user) {
            $this->telegram->answerCallbackQuery($callbackId, "Foydalanuvchi topilmadi. /start buyrug'ini yuboring.", true);
            return response()->json(['status' => 'user_not_found'], 200);
        }

        // Double Click/Double Submit prevention
        $this->telegram->answerCallbackQuery($callbackId);

        // Parse callback commands
        // Format: category:action:id
        $parts = explode(':', $data);
        $category = $parts[0] ?? '';
        $action = $parts[1] ?? '';
        $id = $parts[2] ?? '';

        switch ($category) {
            case 'menu':
                if ($action === 'channels') {
                    $this->showChannelsMenu($user);
                } elseif ($action === 'plans') {
                    $this->showPlansMenu($user);
                } elseif ($action === 'add_channel') {
                    $this->stateManager->setState($user->telegram_id, 'awaiting_channel_id');
                    $this->telegram->sendMessage($user->telegram_id, "📢 **Kanal qo'shish:**\n\n" .
                        "1. Avval botni kanalingizga **admin** qilib qo'shing.\n" .
                        "2. Kanalning **username** havolasini yuboring (masalan: `@kanal_nomi`) yoki kanaldan birorta xabarni shu yerga **forward** (yo'naltiring).");
                }
                break;

            case 'channel':
                if ($action === 'view') {
                    $channel = Channel::find($id);
                    if ($channel) {
                        $text = "📢 **Kanal:** {$channel->title}\n" .
                                "Sarlavha: @{$channel->username}\n" .
                                "Holat: " . ($channel->is_active ? 'Faol' : 'Nofaol') . "\n\n" .
                                "Sozlamalarni o'zgartirish va eski postlarni avtomatik tozalash vaqtini belgilash uchun quyidagi tahrirlash panelidan foydalaning:";
                        
                        $miniAppUrl = env('APP_URL') . "/mini-app/channels/{$channel->id}?tg_id=" . $user->telegram_id;
                        $this->telegram->sendMessage($user->telegram_id, $text, [
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [['text' => '✏️ Batafsil sozlamalar', 'web_app' => ['url' => $miniAppUrl]]],
                                    [['text' => '❌ O\'chirish', 'callback_data' => "channel:delete:{$channel->id}"]],
                                    [['text' => '⬅️ Orqaga', 'callback_data' => 'menu:channels']],
                                ]
                            ])
                        ]);
                    }
                } elseif ($action === 'delete') {
                    $channel = Channel::find($id);
                    if ($channel) {
                        $channel->delete();
                        $this->telegram->sendMessage($user->telegram_id, "✅ Kanal muvaffaqiyatli o'chirildi.");
                        $this->showChannelsMenu($user);
                    }
                }
                break;

            case 'post':
                $post = Post::find($id);
                if (!$post) {
                    $this->telegram->sendMessage($user->telegram_id, "❌ Post topilmadi yoki o'chirib yuborilgan.");
                    break;
                }

                if ($action === 'skip_missing') {
                    $this->telegram->deleteMessage($user->telegram_id, $messageId);
                    $loading = $this->telegram->sendMessage($user->telegram_id, "⏳ **AI post tayyorlamoqda (shablon maydonlarisiz)...**");
                    $loadingMessageId = $loading['result']['message_id'] ?? null;

                    GenerateAiPostJob::dispatch(
                        $user->id,
                        $post->channel_id,
                        $post->draft_content, // original prompt
                        $loadingMessageId,
                        $post->media_type,
                        $post->media_url,
                        true // ignoreMissing = true
                    );

                    $post->delete();
                }
                elseif ($action === 'provide_missing') {
                    $this->telegram->deleteMessage($user->telegram_id, $messageId);
                    $this->stateManager->setState($user->telegram_id, 'awaiting_missing_info', ['post_id' => $post->id]);
                    $this->telegram->sendMessage(
                        $user->telegram_id,
                        "✍️ **Iltimos, yetishmayotgan ma'lumotlarni yozib yuboring.**\n" .
                        "Masalan: `Narxi 12000$, tel: +998901234567` va hokazo."
                    );
                }
                elseif ($action === 'approve') {
                    // Double submit locks: update current buttons
                    $this->telegram->editMessageReplyMarkup($user->telegram_id, $messageId, [
                        'inline_keyboard' => [[['text' => '⏳ Joylashtirilmoqda...', 'callback_data' => 'done']]]
                    ]);

                    // Publish post in background queue immediately
                    PublishPostJob::dispatch($post->id);

                    // Notify user
                    $this->telegram->sendMessage($user->telegram_id, "✅ Post navbatga qo'shildi va tez orada kanalda e'lon qilinadi.");
                } 
                elseif ($action === 'reject') {
                    $post->update(['status' => 'failed']);
                    $this->telegram->deleteMessage($user->telegram_id, $messageId);
                    $this->telegram->sendMessage($user->telegram_id, "❌ Post rad etildi va o'chirildi.");
                } 
                elseif ($action === 'schedule') {
                    // Show quick schedule options
                    $text = "📅 **Postni rejalashtirish:**\n\n" .
                            "Iltimos, post chiqarish uchun mos vaqtni tanlang:";
                    
                    $miniAppUrl = env('APP_URL') . "/mini-app/calendar?tg_id=" . $user->telegram_id . "&post_id=" . $post->id;
                    $this->telegram->sendMessage($user->telegram_id, $text, [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '⏰ 1 soatdan keyin', 'callback_data' => "post:sch_quick_{$post->id}:1"]],
                                [['text' => '🌅 Ertaga 09:00 da', 'callback_data' => "post:sch_quick_{$post->id}:9"]],
                                [['text' => '📅 To\'liq kalendarni ochish', 'web_app' => ['url' => $miniAppUrl]]],
                                [['text' => '✍️ Custom vaqt kiritish (HH:MM formatda)', 'callback_data' => "post:sch_custom_{$post->id}:0"]],
                            ]
                        ])
                    ]);
                }
                elseif (str_starts_with($action, 'sch_quick')) {
                    $hours = $id; // matches 1 or 9
                    $postPart = explode('_', $action);
                    $postId = $postPart[2] ?? null;
                    $post = Post::find($postId);

                    if ($post) {
                        $scheduledAt = Carbon::now();
                        if ($hours == '1') {
                            $scheduledAt->addHour();
                        } elseif ($hours == '9') {
                            $scheduledAt->addDay()->setTime(9, 0, 0);
                        }

                        $post->update([
                            'status' => 'scheduled',
                            'scheduled_at' => $scheduledAt
                        ]);

                        $this->telegram->sendMessage($user->telegram_id, "✅ Post rejalashtirildi: *" . $scheduledAt->format('d.m.Y H:i') . "*");
                        $this->telegram->deleteMessage($user->telegram_id, $messageId);
                    }
                }
                elseif (str_starts_with($action, 'sch_custom')) {
                    $postPart = explode('_', $action);
                    $postId = $postPart[2] ?? null;

                    $this->stateManager->setState($user->telegram_id, 'awaiting_schedule_time', ['post_id' => $postId]);
                    $this->telegram->sendMessage($user->telegram_id, "📅 **Custom vaqt kiritish:**\n\n" .
                        "Iltimos, post e'lon qilinadigan vaqtni quyidagi formatlarda yuboring:\n" .
                        "• `18:30` (Bugun shu soatda)\n" .
                        "• `02.08.2026 15:45` (Belgilangan kunda)\n\n" .
                        "Hozirgi vaqt: *" . Carbon::now()->format('d.m.Y H:i') . "*");
                }
                break;

            case 'admin':
                // Handles receipts checking by admins
                // Format: admin:payment_approve:confirmation_id
                $confirmationId = $id;
                $confirmation = PaymentConfirmation::find($confirmationId);

                if (!$confirmation) {
                    $this->telegram->sendMessage($user->telegram_id, "❌ Kvitansiya topilmadi.");
                    break;
                }

                $admin = Admin::where('email', 'admin@tgmanager.uz')->first(); // Fallback simulation
                $adminId = $admin ? $admin->id : 1;

                if ($action === 'payment_approve') {
                    $provider = new ManualPaymentProvider();
                    $plan = $parts[3] ?? 'premium'; // custom plan passed in button
                    
                    $provider->verifyPayment($confirmation->id, [
                        'status' => 'approved',
                        'plan' => $plan,
                    ]);

                    // Log activity
                    \App\Models\ActivityLog::create([
                        'admin_id' => $adminId,
                        'action' => 'payment_approve',
                        'details' => "Approved manual payment confirmation ID #{$confirmation->id} for user ID {$confirmation->user_id}."
                    ]);

                    $this->telegram->sendMessage($confirmation->user->telegram_id, "🎉 **Tabriklaymiz!** Sizning to'lovingiz tasdiqlandi va **" . ucfirst($plan) . "** tarifi faollashtirildi!");
                    $this->telegram->sendMessage($user->telegram_id, "✅ To'lov tasdiqlandi. Foydalanuvchiga xabar yuborildi.");
                    $this->telegram->deleteMessage($user->telegram_id, $messageId);
                } 
                elseif ($action === 'payment_reject') {
                    $provider = new ManualPaymentProvider();
                    $provider->verifyPayment($confirmation->id, [
                        'status' => 'rejected',
                        'rejection_reason' => 'To\'lov summasi noto\'g\'ri yoki chek soxta.',
                    ]);

                    // Log activity
                    \App\Models\ActivityLog::create([
                        'admin_id' => $adminId,
                        'action' => 'payment_reject',
                        'details' => "Rejected manual payment confirmation ID #{$confirmation->id} for user ID {$confirmation->user_id}."
                    ]);

                    $this->telegram->sendMessage($confirmation->user->telegram_id, "❌ **Kechirasiz!** Siz yuborgan to'lov cheki rad etildi.\nSabab: To'lov summasi noto'g'ri yoki chek soxta. Muammo bo'lsa @admin ga yozing.");
                    $this->telegram->sendMessage($user->telegram_id, "❌ To'lov rad etildi.");
                    $this->telegram->deleteMessage($user->telegram_id, $messageId);
                }
                break;

            case 'stars':
                if ($action === 'invoice') {
                    if ($id === 'manual_premium') {
                        $provider = new ManualPaymentProvider();
                        $result = $provider->createInvoice($user, 50000, 'premium');
                        $this->telegram->sendMessage($user->telegram_id, $result['message']);
                        $this->stateManager->setState($user->telegram_id, 'awaiting_payment_proof');
                    } elseif ($id === 'stars_premium') {
                        $provider = new \App\Services\Payment\TelegramStarsProvider();
                        $result = $provider->createInvoice($user, 150, 'premium');
                        if ($result['success']) {
                            $this->telegram->sendMessage($user->telegram_id, $result['message'], [
                                'reply_markup' => json_encode([
                                    'inline_keyboard' => [
                                        [['text' => '⭐️ To\'lov qilish (150 Stars)', 'url' => $result['data']['invoice_link']]]
                                    ]
                                ])
                            ]);
                        } else {
                            $this->telegram->sendMessage($user->telegram_id, "❌ " . $result['message']);
                        }
                    } elseif ($id === 'stars_business') {
                        $provider = new \App\Services\Payment\TelegramStarsProvider();
                        $result = $provider->createInvoice($user, 400, 'business');
                        if ($result['success']) {
                            $this->telegram->sendMessage($user->telegram_id, $result['message'], [
                                'reply_markup' => json_encode([
                                    'inline_keyboard' => [
                                        [['text' => '⭐️ To\'lov qilish (400 Stars)', 'url' => $result['data']['invoice_link']]]
                                    ]
                                ])
                            ]);
                        } else {
                            $this->telegram->sendMessage($user->telegram_id, "❌ " . $result['message']);
                        }
                    }
                }
                break;
        }

        return response()->json(['status' => 'callback_handled'], 200);
    }

    /**
     * Handle Manual Payment screenshot receipts upload.
     */
    protected function handlePaymentProofUpload(User $user, array $photos)
    {
        // Get the largest image size (which is the last item in array)
        $largestPhoto = end($photos);
        $fileId = $largestPhoto['file_id'];

        try {
            // Get file path from Telegram Bot API
            $fileResponse = Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getFile", [
                'file_id' => $fileId
            ])->json();

            $filePath = $fileResponse['result']['file_path'] ?? null;
            if (!$filePath) {
                throw new Exception("Telegram getFile path null.");
            }

            // Download file
            $fileContent = Http::get("https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/{$filePath}")->body();
            
            // Save to public storage
            $localPath = 'receipts/' . md5($fileId . time()) . '.jpg';
            Storage::disk('public')->put($localPath, $fileContent);

            // Update pending confirmation
            $confirmation = PaymentConfirmation::where('user_id', $user->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($confirmation) {
                $confirmation->update([
                    'screenshot_path' => $localPath
                ]);
            } else {
                $confirmation = PaymentConfirmation::create([
                    'user_id' => $user->id,
                    'screenshot_path' => $localPath,
                    'status' => 'pending',
                ]);
            }

            // Clear Redis State
            $this->stateManager->clearState($user->telegram_id);

            $this->telegram->sendMessage($user->telegram_id, "✅ **To'lov cheki qabul qilindi!**\n\n" .
                "Tez orada adminlarimiz kvitansiyani tekshirib, tarifingizni faollashtirishadi (odatda 15-30 daqiqada).");

            // Notify Admin chat for manual moderation
            $adminChatId = env('TELEGRAM_ADMIN_CHAT_ID');
            if ($adminChatId) {
                $adminMsg = "🔔 **Yangi to'lov tasdiqlash kutilmoqda:**\n\n" .
                            "Foydalanuvchi: {$user->name} (@{$user->username})\n" .
                            "Telegram ID: `{$user->telegram_id}`\n" .
                            "Kvitansiya ID: #{$confirmation->id}\n" .
                            "Summa: " . number_format($confirmation->amount, 0, ',', ' ') . " UZS";

                $photoUrl = asset('storage/' . $localPath);
                
                // Using Telegram bot API to send photo to Admin Group/Chat with inline buttons to approve/reject
                $this->telegram->sendPhoto($adminChatId, $photoUrl, $adminMsg, [
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Tasdiqlash (Premium)', 'callback_data' => "admin:payment_approve:{$confirmation->id}:premium"],
                                ['text' => '✅ Tasdiqlash (Biznes)', 'callback_data' => "admin:payment_approve:{$confirmation->id}:business"]
                            ],
                            [['text' => '❌ Rad etish', 'callback_data' => "admin:payment_reject:{$confirmation->id}"]]
                        ]
                    ])
                ]);
            }

        } catch (Exception $e) {
            Log::channel('payment_errors')->error("Receipt upload failed for user {$user->id}: " . $e->getMessage());
            $this->telegram->sendMessage($user->telegram_id, "❌ To'lov chekini saqlashda xato yuz berdi. Iltimos qaytadan yuborib ko'ring yoki admin bilan bog'laning.");
        }

        return response()->json(['status' => 'payment_proof_handled'], 200);
    }

    /**
     * Connect a new Telegram channel.
     */
    protected function handleChannelConnection(User $user, string $channelInput)
    {
        // Clean channel username/id input
        $channelId = trim($channelInput);

        if (!str_starts_with($channelId, '-') && !str_starts_with($channelId, '@')) {
            $channelId = '@' . $channelId;
        }

        // Verify bot has admin rights
        $check = $this->telegram->checkBotAdminPermission($channelId);

        if (!$check['status']) {
            $this->telegram->sendMessage($user->telegram_id, "❌ **Xatolik:** " . $check['message'] . "\n\n" .
                "Iltimos, bot kanalda admin ekanligiga va 'Post joylash' (Post messages) ruxsati borligiga ishonch hosil qilib, kanal havolasini qaytadan yuboring.");
            return response()->json(['status' => 'channel_connection_failed'], 200);
        }

        try {
            // Resolve channel title & username via API
            // Let's call getChat Telegram API
            $chatInfo = Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getChat", [
                'chat_id' => $channelId
            ])->json();

            $chat = $chatInfo['result'] ?? null;
            if (!$chat) {
                throw new Exception("GetChat returned null.");
            }

            $telegramId = $chat['id'];
            $title = $chat['title'] ?? 'Noma\'lum kanal';
            $username = $chat['username'] ?? null;

            // Check channel limits based on subscription plan
            $channelsCount = $user->channels()->count();
            if ($user->plan === 'free' && $channelsCount >= 1) {
                $this->telegram->sendMessage($user->telegram_id, "⚠️ **Limitga yetdingiz:** Bepul tarifda faqat 1 ta kanal ulashingiz mumkin. Ko'proq ulash uchun tarifingizni yangilang.");
                $this->stateManager->clearState($user->telegram_id);
                return response()->json(['status' => 'channel_limit_reached'], 200);
            }

            // Save channel
            Channel::updateOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'title' => $title,
                    'username' => $username,
                    'owner_id' => $user->id,
                    'is_active' => true,
                    'settings' => [
                        'hashtags' => '',
                        'format_style' => 'default',
                        'auto_delete_hours' => 0, // 0 = don't auto-delete
                    ]
                ]
            );

            // Clear Redis State
            $this->stateManager->clearState($user->telegram_id);

            $this->telegram->sendMessage($user->telegram_id, "🎉 **Kanal muvaffaqiyatli ulandi!**\n\n" .
                "Kanal: *{$title}*\n" .
                "Identifikator: `{$telegramId}`\n\n" .
                "Endi botga xohlagan matningizni yozib yuborishingiz mumkin. Bot sizga chiroyli post yaratib beradi!");

        } catch (Exception $e) {
            Log::channel('telegram_errors')->error("Channel resolution failed: " . $e->getMessage());
            $this->telegram->sendMessage($user->telegram_id, "❌ Kanal ma'lumotlarini olishda xato yuz berdi. Bot kanalingizga a'zo qilinganiga ishonch hosil qiling.");
        }

        return response()->json(['status' => 'channel_connected'], 200);
    }

    /**
     * Handle manual custom date/time scheduling input.
     */
    protected function handleCustomSchedulingTime(User $user, ?int $postId, string $timeInput)
    {
        $post = Post::find($postId);
        if (!$post) {
            $this->telegram->sendMessage($user->telegram_id, "❌ Tahrirlanayotgan post topilmadi.");
            $this->stateManager->clearState($user->telegram_id);
            return response()->json(['status' => 'post_not_found'], 200);
        }

        try {
            $input = trim($timeInput);
            $scheduledAt = null;

            if (preg_match('/^\d{2}:\d{2}$/', $input)) {
                // Format: HH:MM, e.g. 18:30 (today)
                $timeParts = explode(':', $input);
                $scheduledAt = Carbon::now()->setTime($timeParts[0], $timeParts[1], 0);
                if ($scheduledAt->isPast()) {
                    // If time is past, schedule for tomorrow
                    $scheduledAt->addDay();
                }
            } else {
                // Try parse format: d.m.Y H:i, e.g. 02.08.2026 15:30
                $scheduledAt = Carbon::createFromFormat('d.m.Y H:i', $input);
            }

            if (!$scheduledAt || $scheduledAt->isPast()) {
                throw new Exception("Scheduled time is in the past.");
            }

            $post->update([
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt
            ]);

            $this->stateManager->clearState($user->telegram_id);
            $this->telegram->sendMessage($user->telegram_id, "✅ Post rejalashtirildi: *" . $scheduledAt->format('d.m.Y H:i') . "*");

        } catch (Exception $e) {
            $this->telegram->sendMessage($user->telegram_id, "⚠️ **Xato format:** Iltimos vaqtni to'g'ri kiriting, masalan: `18:30` yoki `02.08.2026 15:45` va u kelajakda bo'lishi shart.");
        }

        return response()->json(['status' => 'custom_schedule_handled'], 200);
    }

    /**
     * Handle user providing missing info for a template.
     */
    protected function handleProvidedMissingInfo(User $user, ?int $postId, string $infoInput)
    {
        $post = Post::find($postId);
        if (!$post) {
            $this->telegram->sendMessage($user->telegram_id, "❌ Tahrirlanayotgan post topilmadi.");
            $this->stateManager->clearState($user->telegram_id);
            return response()->json(['status' => 'post_not_found'], 200);
        }

        $this->stateManager->clearState($user->telegram_id);

        $newPrompt = $post->draft_content . "\n\nYetishmayotgan ma'lumotlar:\n" . $infoInput;

        // Send loading message
        $loading = $this->telegram->sendMessage($user->telegram_id, "⏳ **AI postni yangilamoqda...**");
        $loadingMessageId = $loading['result']['message_id'] ?? null;

        // Dispatch new post generation job with full prompt (ignoreMissing = false)
        GenerateAiPostJob::dispatch(
            $user->id,
            $post->channel_id,
            $newPrompt,
            $loadingMessageId,
            $post->media_type,
            $post->media_url,
            false // ignoreMissing = false
        );

        // Delete the temporary pending post
        $post->delete();

        return response()->json(['status' => 'provided_missing_info_handled'], 200);
    }

    /**
     * Handle user post generation prompt.
     */
    protected function handlePostPromptInput(User $user, string $prompt, string $mediaType = 'none', ?string $mediaUrl = null)
    {
        // 1. Check user channels count (user must have at least 1 channel connected)
        $channel = $user->channels()->where('is_active', true)->first();
        if (!$channel) {
            $this->telegram->sendMessage($user->telegram_id, "⚠️ **Kanal topilmadi:** Post yaratishdan oldin kamida bitta kanal ulanishi kerak. Kanal ulash uchun /mychannels bosing.");
            return response()->json(['status' => 'no_channels_connected'], 200);
        }

        // 2. Check Daily Limits
        if ($user->plan === 'free' && $user->daily_used >= $user->daily_limit) {
            $this->telegram->sendMessage($user->telegram_id, "⚠️ **Limit tugadi:** Bepul tarif bo'yicha kunlik 5 ta post limiti tugagan. Tarifni yangilash uchun /pay buyrug'ini ishlating.");
            return response()->json(['status' => 'daily_limit_exceeded'], 200);
        }

        // Send intermediate loading message to double-submit protect and notify user
        $loading = $this->telegram->sendMessage($user->telegram_id, "⏳ **AI post tayyorlamoqda...**\nIltimos kuting, bu taxminan 5-10 soniya vaqt oladi.");
        $loadingMessageId = $loading['result']['message_id'] ?? null;

        // Dispatch background job for AI call and similarity evaluation to avoid Webhook timeout (sync flow)
        GenerateAiPostJob::dispatch($user->id, $channel->id, $prompt, $loadingMessageId, $mediaType, $mediaUrl);

        return response()->json(['status' => 'post_generation_job_dispatched'], 200);
    }

    /**
     * Show list of connected channels to user.
     */
    protected function showChannelsMenu(User $user)
    {
        $channels = $user->channels;
        $keyboard = [];

        if ($channels->isEmpty()) {
            $text = "📢 **Sizning kanallaringiz:**\n\n" .
                    "Hozircha hech qanday kanal ulanmagan.";
        } else {
            $text = "📢 **Sizning kanallaringiz:**\n\n" .
                    "Kanal sozlamalarini boshqarish uchun mos kanalni tanlang:";
            
            foreach ($channels as $channel) {
                $keyboard[] = [[
                    'text' => "📺 {$channel->title} (@{$channel->username})",
                    'callback_data' => "channel:view:{$channel->id}"
                ]];
            }
        }

        $keyboard[] = [['text' => '➕ Yangi kanal ulash', 'callback_data' => 'menu:add_channel']];

        $this->telegram->sendMessage($user->telegram_id, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ])
        ]);
    }

    /**
     * Show subscription plans and checkout triggers.
     */
    protected function showPlansMenu(User $user)
    {
        $text = "💎 **Premium obuna tariflari:**\n\n" .
                "🚀 **1. PREMIUM TARIF**\n" .
                "• Cheksiz AI post yaratish\n" .
                "• Avtomatik postlarni rejalashtirish\n" .
                "• Semantik dublikat aniqlash\n" .
                "• Taqvim ko'rinishidagi postlar kalendari\n" .
                "💸 Narxi: *50 000 UZS / oy* yoki *150 Stars*\n\n" .
                "💼 **2. BIZNES TARIF**\n" .
                "• Cheksiz kanallar va operatorlar qo'shish\n" .
                "• Telegram Web App admin rollari\n" .
                "💸 Narxi: *150 000 UZS / oy* yoki *400 Stars*\n\n" .
                "Joriy tarifingiz: *" . strtoupper($user->plan) . "*\n\n" .
                "Quyidagi to'lov usullaridan birini tanlang:";

        $this->telegram->sendMessage($user->telegram_id, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '💳 UZS (Qo\'lda karta orqali)', 'callback_data' => 'stars:invoice:manual_premium']
                    ],
                    [
                        ['text' => '⭐️ Stars (Premium - 150 ⭐️)', 'callback_data' => 'stars:invoice:stars_premium'],
                        ['text' => '⭐️ Stars (Biznes - 400 ⭐️)', 'callback_data' => 'stars:invoice:stars_business']
                    ]
                ]
            ])
        ]);
    }

    /**
     * Handle incoming inline stars/payment triggers.
     */
    protected function handleSuccessfulPayment(User $user, array $payment)
    {
        try {
            $payload = json_decode($payment['invoice_payload'], true);
            $telegramChargeId = $payment['telegram_payment_charge_id'];

            $provider = new \App\Services\Payment\TelegramStarsProvider();
            $provider->verifyPayment($telegramChargeId, [
                'user_id' => $user->id,
                'plan' => $payload['plan'] ?? 'premium',
                'amount' => $payload['amount'] ?? 0,
                'telegram_payment_charge_id' => $telegramChargeId,
            ]);

            $this->telegram->sendMessage($user->telegram_id, "🎉 **Obuna muvaffaqiyatli faollashtirildi!**\n\n" .
                "Kanal boshqaruvidan unumli foydalaning!");

        } catch (Exception $e) {
            Log::channel('payment_errors')->error("Telegram Stars webhook verification failed: " . $e->getMessage());
        }

        return response()->json(['status' => 'payment_success_handled'], 200);
    }
}
