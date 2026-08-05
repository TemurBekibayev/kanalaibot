<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    protected $fillable = [
        'telegram_id',
        'title',
        'username',
        'owner_id',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'owner_id' => 'integer',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The Telegram owner of this channel.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Posts belongs to this channel.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
