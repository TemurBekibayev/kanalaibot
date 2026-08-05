<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'telegram_id',
        'username',
        'name',
        'plan',
        'daily_limit',
        'daily_used',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'daily_limit' => 'integer',
        'daily_used' => 'integer',
    ];

    /**
     * Channels owned by this user.
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'owner_id');
    }

    /**
     * AI usage history logs for this user.
     */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * Subscriptions bought by this user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Payment proof screenshot submissions by this user.
     */
    public function paymentConfirmations(): HasMany
    {
        return $this->hasMany(PaymentConfirmation::class);
    }
}
