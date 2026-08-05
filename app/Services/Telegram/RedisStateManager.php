<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Redis;

class RedisStateManager
{
    protected string $prefix = 'bot:state:';
    protected int $ttl = 1800; // 30 minutes in seconds

    /**
     * Set state for a user.
     */
    public function setState(int $telegramId, string $step, array $data = []): void
    {
        $key = $this->prefix . $telegramId;
        $payload = json_encode([
            'step' => $step,
            'data' => $data,
            'updated_at' => time()
        ]);

        Redis::setex($key, $this->ttl, $payload);
    }

    /**
     * Get current state for a user.
     *
     * @return array{step: string, data: array}|null
     */
    public function getState(int $telegramId): ?array
    {
        $key = $this->prefix . $telegramId;
        $data = Redis::get($key);

        if (!$data) {
            return null;
        }

        return json_decode($data, true);
    }

    /**
     * Clear state for a user.
     */
    public function clearState(int $telegramId): void
    {
        $key = $this->prefix . $telegramId;
        Redis::del($key);
    }
}
