<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfirmation extends Model
{
    protected $fillable = [
        'user_id',
        'screenshot_path',
        'amount',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    /**
     * User who submitted this confirmation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
