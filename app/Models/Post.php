<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'channel_id',
        'draft_content',
        'final_content',
        'status',
        'media_type',
        'media_url',
        'scheduled_at',
        'posted_at',
        'ai_provider',
        'tokens_used',
        'cost',
        'meta',
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'scheduled_at' => 'datetime',
        'posted_at' => 'datetime',
        'tokens_used' => 'integer',
        'cost' => 'decimal:4',
        'meta' => 'array',
    ];

    /**
     * The channel this post belongs to.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Checks checking similarity for this post.
     */
    public function duplicateChecks(): HasMany
    {
        return $this->hasMany(DuplicateCheck::class, 'post_id');
    }
}
