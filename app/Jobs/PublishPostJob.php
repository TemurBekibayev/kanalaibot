<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $postId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $postId)
    {
        $this->postId = $postId;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramBotService $telegram): void
    {
        $post = Post::find($this->postId);

        if (!$post) {
            Log::error("PublishPostJob aborted: Post ID {$this->postId} not found.");
            return;
        }

        if ($post->status === 'posted') {
            Log::info("Post ID {$this->postId} has already been published. Skipping.");
            return;
        }

        try {
            $channel = $post->channel;
            $content = $post->final_content ?? $post->draft_content;

            // 1. Append default hashtags from channel settings
            $settings = $channel->settings ?? [];
            $hashtags = $settings['hashtags'] ?? '';
            
            if (!empty($hashtags)) {
                $content .= "\n\n" . trim($hashtags);
            }

            // 2. Apply channel format style
            $formatStyle = $settings['format_style'] ?? 'default';
            if ($formatStyle === 'bold') {
                // If bold is selected, wrap paragraphs or text in markdown bold
                // For simplicity, we just pass Markdown parse mode which handles existing bold markers
            }

            // 3. Publish to Telegram Channel
            $result = [];
            $mediaTarget = $post->meta['telegram_file_id'] ?? $post->media_url;

            if ($post->media_type === 'photo' && !empty($mediaTarget)) {
                $result = $telegram->sendPhoto($channel->telegram_id, $mediaTarget, $content);
            } elseif ($post->media_type === 'video' && !empty($mediaTarget)) {
                $result = $telegram->sendVideo($channel->telegram_id, $mediaTarget, $content);
            } else {
                $result = $telegram->sendMessage($channel->telegram_id, $content);
            }

            $ok = $result['ok'] ?? false;
            if (!$ok) {
                throw new Exception("Telegram Bot API failed to publish: " . json_encode($result));
            }

            // 4. Save telegram message ID in meta (critical for auto-deletion!)
            $messageId = $result['result']['message_id'] ?? null;
            $meta = $post->meta ?? [];
            $meta['telegram_message_id'] = $messageId;

            $post->update([
                'status' => 'posted',
                'posted_at' => Carbon::now(),
                'meta' => $meta,
            ]);

            Log::info("Post ID {$post->id} published successfully to channel {$channel->title}. Message ID: {$messageId}");

        } catch (Exception $e) {
            $post->update(['status' => 'failed']);
            Log::channel('telegram_errors')->error("Failed to publish Post ID {$post->id}: " . $e->getMessage(), [
                'post_id' => $post->id,
                'channel_id' => $post->channel_id,
            ]);
        }
    }
}
