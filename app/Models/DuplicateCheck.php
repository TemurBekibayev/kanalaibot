<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateCheck extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'compared_post_id',
        'similarity_score',
        'check_type',
    ];

    protected $casts = [
        'post_id' => 'integer',
        'compared_post_id' => 'integer',
        'similarity_score' => 'float',
    ];

    /**
     * Primary post checked.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * Post compared against.
     */
    public function comparedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'compared_post_id');
    }
}
