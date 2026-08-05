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

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, int $channelId, string $prompt, ?int $loadingMessageId = null)
    {
        $this->userId = $userId;
        $this->channelId = $channelId;
        $this->prompt = $prompt;
        $this->loadingMessageId = $loadingMessageId;
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
            $aiPrompt = "Siz tajribali Telegram kanal menejerisiz. Foydalanuvchi sizga qisqa e'lon matnini yuboradi. Siz bu matndan chiroyli formatlangan, o'quvchini jalb qiluvchi, mos emojilar va tegishli hashtaglar bilan to'liq post yaratishingiz kerak.\n\n" .
                        "Mavzu: Telegram kanal e'loni\n" .
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

            // 3. Create draft record in DB
            $post = Post::create([
                'channel_id' => $channel->id,
                'draft_content' => $aiResult['text'],
                'final_content' => $aiResult['text'],
                'status' => 'draft',
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
            
            $telegram->sendMessage($user->telegram_id, $previewMsg, [
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
            ]);

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
