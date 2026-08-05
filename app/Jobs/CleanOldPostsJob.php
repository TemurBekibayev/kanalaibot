<?php

namespace App\Jobs;

use App\Models\Channel;
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

class CleanOldPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(TelegramBotService $telegram): void
    {
        Log::info("CleanOldPostsJob: starting auto-cleanup check.");

        // Find all channels with auto delete hours configured > 0
        $channels = Channel::where('is_active', true)->get();

        foreach ($channels as $channel) {
            $settings = $channel->settings ?? [];
            $autoDeleteHours = (int) ($settings['auto_delete_hours'] ?? 0);

            if ($autoDeleteHours <= 0) {
                continue;
            }

            // Calculate expiration cutoff time
            $cutoff = Carbon::now()->subHours($autoDeleteHours);

            // Fetch published posts that have passed the cutoff
            $expiredPosts = Post::where('channel_id', $channel->id)
                ->where('status', 'posted')
                ->where('posted_at', '<=', $cutoff)
                ->get();

            if ($expiredPosts->isEmpty()) {
                continue;
            }

            Log::info("CleanOldPostsJob: found {$expiredPosts->count()} expired posts for channel: {$channel->title}");

            foreach ($expiredPosts as $post) {
                $meta = $post->meta ?? [];
                $messageId = $meta['telegram_message_id'] ?? null;

                if ($messageId) {
                    try {
                        // Delete the message from Telegram channel
                        $deleted = $telegram->deleteMessage($channel->telegram_id, (int) $messageId);
                        
                        if ($deleted) {
                            $post->update(['status' => 'archived']);
                            Log::info("Auto-cleanup: Deleted post ID {$post->id} from channel {$channel->title}. Message ID: {$messageId}");
                        } else {
                            // If delete failed (e.g. message too old or already deleted), mark it as failed to prevent retries
                            $post->update(['status' => 'archived']); 
                            Log::warning("Auto-cleanup: Could not delete post ID {$post->id} from channel {$channel->title}. Message might not exist.");
                        }
                    } catch (Exception $e) {
                        Log::channel('telegram_errors')->error("Auto-cleanup error for post ID {$post->id}: " . $e->getMessage());
                    }
                } else {
                    // No message ID stored, just archive it in DB
                    $post->update(['status' => 'archived']);
                }
            }
        }
    }
}
