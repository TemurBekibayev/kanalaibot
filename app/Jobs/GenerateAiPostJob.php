<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Channel;
use App\Models\Post;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Duplicate\DuplicateDetectorInterface;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class GenerateAiPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected int $channelId;
    protected string $prompt;
    protected ?int $loadingMessageId;
    protected string $mediaType;
    protected ?string $mediaUrl;
    protected bool $ignoreMissing;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $userId,
        int $channelId,
        string $prompt,
        ?int $loadingMessageId = null,
        string $mediaType = 'none',
        ?string $mediaUrl = null,
        bool $ignoreMissing = false
    ) {
        $this->userId = $userId;
        $this->channelId = $channelId;
        $this->prompt = $prompt;
        $this->loadingMessageId = $loadingMessageId;
        $this->mediaType = $mediaType;
        $this->mediaUrl = $mediaUrl;
        $this->ignoreMissing = $ignoreMissing;
    }

    /**
     * Execute the job.
     */
    public function handle(
        AiProviderInterface $aiService,
        DuplicateDetectorInterface $duplicateDetector,
        TelegramBotService $telegram
    ): void {
        $user = User::find($this->userId);
        $channel = Channel::find($this->channelId);

        if (!$user || !$channel) {
            Log::error("GenerateAiPostJob aborted: User or Channel not found.");
            return;
        }

        try {
            // 1. Construct prompt for AI
            $settings = $channel->settings ?? [];
            $customTemplate = $settings['custom_template'] ?? null;

            $aiPrompt = "Siz tajribali Telegram kanal menejerisiz. Foydalanuvchi sizga qisqa e'lon matnini yuboradi. Siz bu matndan chiroyli formatlangan, o'quvchini jalb qiluvchi, mos emojilar va tegishli hashtaglar bilan to'liq post yaratishingiz kerak.\n\n";

            if (!empty($customTemplate)) {
                $aiPrompt .= "MUHIM QOIDA: Siz ushbu postni faqat va faqat quyidagi qat'iy shablon va yo'riqnoma asosida yaratishingiz kerak. Strukturani mutlaqo o'zgartirmang, barcha yozuvlar, tartib va emojilar shablondagidek qolishi shart:\n" .
                             "\"{$customTemplate}\"\n\n";

                if (!$this->ignoreMissing) {
                    $aiPrompt .= "MA'LUMOTLARNI TEKSHIRISH QOIDASI:\n" .
                                 "Foydalanuvchi matnini shablon bilan solishtiring. Agar shablondagi muhim va zarur maydonlar (masalan: telefon raqami, narx, krasqa holati, yili, manzili yoki shunga o'xshash aniq ma'lumotlar) foydalanuvchi yuborgan matnda umuman yo'qligi aniqlansa, postni yaratmang! Uning o'rniga faqat va faqat quyidagi formatda javob bering:\n" .
                                 "MISSING_INFO: [etishmayotgan maydonlar ro'yxati]\n" .
                                 "Masalan: MISSING_INFO: Narxi, Telefon raqami\n\n";
                }
            }

            $aiPrompt .= "Mavzu: Telegram kanal e'loni\n" .
                        "Asl matn: \"{$this->prompt}\"\n\n" .
                        "Qoidalar:\n" .
                        "- Sarlavhani chiroyli emojilar bilan yozing.\n" .
                        "- Muhim ma'lumotlarni alohida qatorlarda, chiroyli belgilarda ko'rsating.\n" .
                        "- Matn oxiriga mavzuga mos 3-5 ta hashtag qo'shing.\n" .
                        "- Faqat tayyor post matnini qaytaring, ortiqcha gap yozmang.";

            // 2. Generate content via Fallback AI
            $aiResult = $aiService->generatePost($aiPrompt, [
                'user_id' => $user->id,
                'action' => 'post_generation',
            ]);

            $textResult = trim($aiResult['text']);

            if (str_starts_with($textResult, 'MISSING_INFO:')) {
                // Delete loading message if exists
                if ($this->loadingMessageId) {
                    $telegram->deleteMessage($user->telegram_id, $this->loadingMessageId);
                }

                // Create a temporary post with status 'pending_info'
                $post = Post::create([
                    'channel_id' => $channel->id,
                    'draft_content' => $this->prompt, // store original prompt
                    'final_content' => $textResult, // store the missing info string
                    'status' => 'pending_info',
                    'media_type' => $this->mediaType,
                    'media_url' => $this->mediaUrl,
                    'ai_provider' => $aiResult['provider'],
                    'tokens_used' => $aiResult['prompt_tokens'] + $aiResult['completion_tokens'],
                    'cost' => $aiResult['cost'],
                ]);

                // Extract missing fields
                $missingFields = trim(substr($textResult, strlen('MISSING_INFO:')));

                $msg = "⚠️ **Post shablonidagi quyidagi ma'lumotlar topilmadi:**\n\n" .
                       "• {$missingFields}\n\n" .
                       "Shusiz ham postni tayyorlayveraymi yoki ma'lumotni kiritasizmi?";

                $params = [
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Shusiz tayyorla', 'callback_data' => "post:skip_missing:{$post->id}"],
                                ['text' => '✍️ Ma\'lumot kiritaman', 'callback_data' => "post:provide_missing:{$post->id}"]
                            ]
                        ]
                    ])
                ];

                $telegram->sendMessage($user->telegram_id, $msg, $params);
                return;
            }

            // 3. Create draft record in DB
            $post = Post::create([
                'channel_id' => $channel->id,
                'draft_content' => $textResult,
                'final_content' => $textResult,
                'status' => 'draft',
                'media_type' => $this->mediaType,
                'media_url' => $this->mediaUrl,
                'ai_provider' => $aiResult['provider'],
                'tokens_used' => $aiResult['prompt_tokens'] + $aiResult['completion_tokens'],
                'cost' => $aiResult['cost'],
            ]);

            // 4. Run Duplicate Check
            $dupResult = $duplicateDetector->checkDuplicate($post);

            // Delete loading message if exists
            if ($this->loadingMessageId) {
                $telegram->deleteMessage($user->telegram_id, $this->loadingMessageId);
            }

            // 5. Construct preview message to send back to user
            $previewMsg = "🤖 **AI Tayyorlagan Post:**\n\n" .
                          "----------------------------------\n" .
                          "{$post->draft_content}\n" .
                          "----------------------------------\n\n";

            if ($dupResult['is_duplicate']) {
                $previewMsg .= "⚠️ **Diqqat: Dublikat ehtimoli!**\n" .
                               "Kanalingizdagi boshqa post bilan o'xshashlik darajasi: *{$dupResult['similarity_score']}%*.\n\n";
            }

            $previewMsg .= "Quyidagi tugmalardan birini tanlang:";

            // Set up inline keyboards
            $miniAppUrl = env('APP_URL') . "/mini-app/calendar?tg_id=" . $user->telegram_id . "&post_id=" . $post->id;
            
            $params = [
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Kanalga Joylash', 'callback_data' => "post:approve:{$post->id}"],
                            ['text' => '📅 Rejalashtirish', 'callback_data' => "post:schedule:{$post->id}"]
                        ],
                        [
                            ['text' => '✏️ Batafsil tahrirlash (Web)', 'web_app' => ['url' => $miniAppUrl]],
                            ['text' => '❌ Rad etish', 'callback_data' => "post:reject:{$post->id}"]
                        ]
                    ]
                ])
            ];

            if ($post->media_type === 'photo' && !empty($post->media_url)) {
                $telegram->sendPhoto($user->telegram_id, $post->media_url, $previewMsg, $params);
            } elseif ($post->media_type === 'video' && !empty($post->media_url)) {
                $telegram->sendVideo($user->telegram_id, $post->media_url, $previewMsg, $params);
            } else {
                $telegram->sendMessage($user->telegram_id, $previewMsg, $params);
            }

        } catch (Exception $e) {
            Log::channel('ai_errors')->error("GenerateAiPostJob failed: " . $e->getMessage(), [
                'exception' => $e
            ]);

            if ($this->loadingMessageId) {
                $telegram->deleteMessage($user->telegram_id, $this->loadingMessageId);
            }

            $telegram->sendMessage($user->telegram_id, "❌ **Xatolik:** AI post generatsiya qilish jarayonida muammo yuz berdi. Iltimos keyinroq qayta urinib ko'ring.");
        }
    }
}
