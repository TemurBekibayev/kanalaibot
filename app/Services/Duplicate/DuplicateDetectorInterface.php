<?php

namespace App\Services\Duplicate;

use App\Models\Post;

interface DuplicateDetectorInterface
{
    /**
     * Compare a post draft against recent posts in the target channel to spot duplicates.
     *
     * @param Post $post
     * @return array{
     *     is_duplicate: bool,
     *     similarity_score: float,
     *     compared_post_id: ?int
     * }
     */
    public function checkDuplicate(Post $post): array;
}
