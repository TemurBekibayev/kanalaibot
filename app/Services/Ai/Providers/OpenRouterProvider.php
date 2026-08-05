<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class OpenRouterProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.key', env('OPENROUTER_API_KEY'));
        $this->model = config('services.openrouter.model', 'deepseek/deepseek-chat'); // DeepSeek-V3
    }

    public function generatePost(string $prompt, array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new Exception("OpenRouter API key is not configured.");
        }

        $url = "https://openrouter.ai/api/v1/chat/completions";

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url', 'http://localhost'),
            'X-Title' => 'AI Kanal Manager Bot',
        ])->post($url, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000,
        ]);

        if ($response->failed()) {
            throw new Exception("OpenRouter API request failed: " . $response->body(), $response->status());
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!$text) {
            throw new Exception("OpenRouter API returned an empty response: " . json_encode($data));
        }

        $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
        $completionTokens = $data['usage']['completion_tokens'] ?? 0;

        // Calculate approximate cost for DeepSeek V3 ($0.14 per 1M input, $0.28 per 1M output tokens)
        $inputCost = ($promptTokens / 1_000_000) * 0.14;
        $outputCost = ($completionTokens / 1_000_000) * 0.28;
        $totalCost = $inputCost + $outputCost;

        return [
            'text' => trim($text),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'model' => $this->model,
            'provider' => 'openrouter',
            'cost' => round($totalCost, 6),
        ];
    }
}
