<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    // Custom timestamp behavior: only created_at is needed
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'action',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'cost' => 'decimal:4',
    ];

    /**
     * User who initiated the AI command.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
