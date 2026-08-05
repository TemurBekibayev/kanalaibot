<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Channel;
use App\Models\Post;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class MiniAppApiController extends Controller
{
    /**
     * Retrieve authenticated user context.
     */
    protected function getTelegramUser(Request $request): User
    {
        return $request->attributes->get('telegram_user');
    }

    /**
     * GET /api/mini-app/posts
     * Fetch scheduled/draft posts for calendar and lists.
     */
    public function getPosts(Request $request)
    {
        $user = $this->getTelegramUser($request);
        $channelIds = $user->channels()->pluck('id');

        $posts = Post::whereIn('channel_id', $channelIds)
            ->with('channel')
            ->orderBy('scheduled_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'posts' => $posts->map(fn($post) => [
                'id' => $post->id,
                'channel_id' => $post->channel_id,
                'channel_title' => $post->channel->title,
                'draft_content' => $post->draft_content,
                'final_content' => $post->final_content ?? $post->draft_content,
                'status' => $post->status,
                'media_type' => $post->media_type,
                'media_url' => $post->media_url,
                'scheduled_at' => $post->scheduled_at ? $post->scheduled_at->toIso8601String() : null,
                'posted_at' => $post->posted_at ? $post->posted_at->toIso8601String() : null,
            ])
        ]);
    }

    /**
     * POST /api/mini-app/posts/{id}/edit
     * Edit draft or scheduled post details (content, schedule time, status).
     */
    public function editPost(Request $request, int $id)
    {
        $user = $this->getTelegramUser($request);
        $post = Post::where('id', $id)->first();

        if (!$post || $post->channel->owner_id !== $user->id) {
            return response()->json(['message' => 'Post not found or unauthorized.'], 403);
        }

        $validated = $request->validate([
            'final_content' => 'required|string',
            'scheduled_at' => 'nullable|date',
            'status' => 'required|string|in:draft,scheduled,posted,failed',
        ]);

        try {
            $scheduledAt = $validated['scheduled_at'] ? Carbon::parse($validated['scheduled_at']) : null;

            $post->update([
                'final_content' => $validated['final_content'],
                'scheduled_at' => $scheduledAt,
                'status' => $validated['status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post muvaffaqiyatli tahrirlandi!',
                'post' => $post
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Tahrirlashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mini-app/channels
     * Get user channels and their configuration settings.
     */
    public function getChannels(Request $request)
    {
        $user = $this->getTelegramUser($request);
        $channels = $user->channels;

        return response()->json([
            'success' => true,
            'channels' => $channels
        ]);
    }

    /**
     * POST /api/mini-app/channels/{id}/settings
     * Save channel settings (hashtags, auto cleanup configuration).
     */
    public function saveChannelSettings(Request $request, int $id)
    {
        $user = $this->getTelegramUser($request);
        $channel = Channel::where('id', $id)->where('owner_id', $user->id)->first();

        if (!$channel) {
            return response()->json(['message' => 'Kanal topilmadi.'], 404);
        }

        $validated = $request->validate([
            'hashtags' => 'nullable|string',
            'auto_delete_hours' => 'required|integer|min:0',
            'format_style' => 'required|string|in:default,bold,italic',
        ]);

        try {
            $settings = $channel->settings ?? [];
            $settings['hashtags'] = $validated['hashtags'];
            $settings['auto_delete_hours'] = $validated['auto_delete_hours'];
            $settings['format_style'] = $validated['format_style'];

            $channel->update([
                'settings' => $settings
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sozlamalar saqlandi!'
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Sozlamalarni saqlashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mini-app/stats
     * Fetch aggregated usage logs for statistics.
     */
    public function getStats(Request $request)
    {
        $user = $this->getTelegramUser($request);

        // Fetch AI Usage breakdown for last 7 days
        $aiUsage = AiUsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(prompt_tokens + completion_tokens) as total_tokens'),
                DB::raw('COUNT(*) as total_requests')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Fetch published posts stats
        $publishedCount = Post::whereIn('channel_id', $user->channels()->pluck('id'))
            ->where('status', 'posted')
            ->count();

        $scheduledCount = Post::whereIn('channel_id', $user->channels()->pluck('id'))
            ->where('status', 'scheduled')
            ->count();

        return response()->json([
            'success' => true,
            'summary' => [
                'published_posts' => $publishedCount,
                'scheduled_posts' => $scheduledCount,
                'plan' => $user->plan,
                'daily_used' => $user->daily_used,
                'daily_limit' => $user->daily_limit,
            ],
            'charts' => [
                'ai_usage' => $aiUsage
            ]
        ]);
    }

    /**
     * GET /api/mini-app/business/operators
     * Fetch channel admins or operator accounts.
     */
    public function getOperators(Request $request)
    {
        $user = $this->getTelegramUser($request);
        
        if ($user->plan !== 'business') {
            return response()->json(['message' => 'Faqat Biznes tarif foydalanuvchilari uchun.'], 403);
        }

        // Return a mock operator config list, or fetch operators from channels
        // In business setup, operators can manage owner's channel
        return response()->json([
            'success' => true,
            'operators' => [
                [
                    'id' => 1,
                    'name' => 'Operator A',
                    'username' => 'operator_a',
                    'role' => 'editor',
                    'added_at' => Carbon::now()->subDays(5)->toIso8601String(),
                ],
                [
                    'id' => 2,
                    'name' => 'Operator B',
                    'username' => 'operator_b',
                    'role' => 'moderator',
                    'added_at' => Carbon::now()->subDays(2)->toIso8601String(),
                ]
            ]
        ]);
    }
}
