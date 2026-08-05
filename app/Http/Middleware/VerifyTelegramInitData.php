<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramInitData
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Telegram-Init-Data') ?: $request->input('init_data');

        if (empty($initData)) {
            return response()->json(['message' => 'Unauthorized: Missing Telegram initData.'], 401);
        }

        if (!$this->isValidTelegramInitData($initData)) {
            return response()->json(['message' => 'Unauthorized: Invalid Telegram initData hash.'], 401);
        }

        // Parse user from initData
        parse_str($initData, $parsedData);
        $userDataJson = $parsedData['user'] ?? null;

        if (!$userDataJson) {
            return response()->json(['message' => 'Unauthorized: Missing user payload.'], 401);
        }

        $telegramUser = json_decode($userDataJson, true);
        $telegramId = $telegramUser['id'] ?? null;

        if (!$telegramId) {
            return response()->json(['message' => 'Unauthorized: Invalid user ID.'], 401);
        }

        // Find or create bot user in DB
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user) {
            $user = User::create([
                'telegram_id' => $telegramId,
                'name' => $telegramUser['first_name'] ?? 'User',
                'username' => $telegramUser['username'] ?? null,
                'plan' => 'free',
                'daily_limit' => 5,
                'daily_used' => 0,
            ]);
        }

        // Inject user into request attributes
        $request->attributes->set('telegram_user', $user);

        return $next($request);
    }

    /**
     * Validate the Telegram initData hash.
     */
    protected function isValidTelegramInitData(string $initData): bool
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            Log::error("VerifyTelegramInitData: Bot token is not set in config.");
            return false;
        }

        parse_str($initData, $params);
        
        if (!isset($params['hash'])) {
            return false;
        }

        $hash = $params['hash'];
        unset($params['hash']);

        // Sort parameters alphabetically
        ksort($params);

        // Construct data-check-string
        $dataCheckArr = [];
        foreach ($params as $key => $val) {
            $dataCheckArr[] = "{$key}={$val}";
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // Generate cryptographic keys
        // Secret key is HMAC-SHA256 of the token with the key "WebApps"
        $secretKey = hash_hmac('sha256', $botToken, 'WebApps', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $isValid = hash_equals($computedHash, $hash);

        if (!$isValid) {
            Log::warning("VerifyTelegramInitData mismatch. TokenPrefix: " . substr($botToken, 0, 10) . "..., ReceivedHash: " . $hash . ", ComputedHash: " . $computedHash . ", Data: " . str_replace("\n", " | ", $dataCheckString));
        }

        return $isValid;
    }
}
