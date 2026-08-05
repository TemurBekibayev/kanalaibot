<?php

namespace App\Services\Duplicate;

use App\Models\Post;
use App\Models\DuplicateCheck;
use Illuminate\Support\Facades\Log;

class FuzzyDuplicateDetector implements DuplicateDetectorInterface
{
    protected float $threshold = 75.0; // 75% similarity threshold

    public function checkDuplicate(Post $post): array
    {
        $channelId = $post->channel_id;

        // Fetch the last 30 successfully posted messages in this channel
        $recentPosts = Post::where('channel_id', $channelId)
            ->where('status', 'posted')
            ->where('id', '!=', $post->id)
            ->latest()
            ->take(30)
            ->get();

        if ($recentPosts->isEmpty()) {
            return [
                'is_duplicate' => false,
                'similarity_score' => 0.0,
                'compared_post_id' => null
            ];
        }

        $draftText = $this->normalizeText($post->draft_content);
        $highestScore = 0.0;
        $matchedPostId = null;

        foreach ($recentPosts as $recent) {
            $recentText = $this->normalizeText($recent->final_content ?? $recent->draft_content);
            
            // Calculate similarity percentage
            similar_text($draftText, $recentText, $percent);

            if ($percent > $highestScore) {
                $highestScore = $percent;
                $matchedPostId = $recent->id;
            }
        }

        $isDuplicate = $highestScore >= $this->threshold;

        // If similarity is high, log it in duplicate_checks table
        if ($highestScore >= 35.0 && $matchedPostId) {
            try {
                DuplicateCheck::create([
                    'post_id' => $post->id,
                    'compared_post_id' => $matchedPostId,
                    'similarity_score' => $highestScore,
                    'check_type' => 'fuzzy',
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to log duplicate check: " . $e->getMessage());
            }
        }

        return [
            'is_duplicate' => $isDuplicate,
            'similarity_score' => round($highestScore, 2),
            'compared_post_id' => $matchedPostId
        ];
    }

    /**
     * Clean and normalize text to improve comparison accuracy.
     */
    protected function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Remove hashtags and emojis
        $text = preg_replace('/#[a-z0-9_]+/iu', '', $text);
        
        // Remove common punctuation and characters
        $text = preg_replace('/[.,\-\/#!$%\^&\*;:{}=\-_`~()?"\'’]/u', '', $text);

        // Normalize multiple spaces to single
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
