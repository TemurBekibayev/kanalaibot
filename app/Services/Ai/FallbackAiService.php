<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\GroqProvider;
use App\Services\Ai\Providers\OpenRouterProvider;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class FallbackAiService implements AiProviderInterface
{
    protected array $providersOrder;

    public function __construct()
    {
        $orderEnv = env('AI_FALLBACK_ORDER', 'gemini,groq,openrouter');
        $this->providersOrder = array_filter(array_map('trim', explode(',', $orderEnv)));
    }

    /**
     * Generate content trying providers in order.
     */
    public function generatePost(string $prompt, array $options = []): array
    {
        $lastException = null;

        foreach ($this->providersOrder as $providerName) {
            try {
                $provider = $this->resolveProvider($providerName);
                if (!$provider) {
                    continue;
                }

                Log::info("Attempting AI generation with provider: {$providerName}");
                $result = $provider->generatePost($prompt, $options);

                // Successfully generated post, log the usage if user_id is provided
                if (isset($options['user_id'])) {
                    $this->logUsage($options['user_id'], $result, $options['action'] ?? 'post_generation');
                }

                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                Log::channel('ai_errors')->error("AI Provider [{$providerName}] failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'prompt' => $prompt
                ]);
                Log::warning("AI Provider [{$providerName}] failed. Moving to next provider in chain.");
            }
        }

        throw new Exception("All AI providers in fallback chain failed. Last error: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));
    }

    /**
     * Resolve provider instances.
     */
    protected function resolveProvider(string $name): ?AiProviderInterface
    {
        return match (strtolower($name)) {
            'gemini' => new GeminiProvider(),
            'groq' => new GroqProvider(),
            'openrouter' => new OpenRouterProvider(),
            default => null,
        };
    }

    /**
     * Log AI usage to database and update user daily count.
     */
    protected function logUsage(int $userId, array $result, string $action): void
    {
        try {
            // Write usage logs
            AiUsageLog::create([
                'user_id' => $userId,
                'provider' => $result['provider'],
                'model' => $result['model'],
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'cost' => $result['cost'],
                'action' => $action,
            ]);

            // Increment daily used counter for user
            $user = User::find($userId);
            if ($user) {
                $user->increment('daily_used');
            }
        } catch (Exception $e) {
            Log::channel('ai_errors')->error("Failed to log AI usage to database: " . $e->getMessage());
        }
    }
}
