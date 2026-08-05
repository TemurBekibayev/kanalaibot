<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class GeminiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $this->model = config('services.gemini.model', 'gemini-1.5-flash'); // Fallback model
    }

    public function generatePost(string $prompt, array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new Exception("Gemini API key is not configured.");
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 1000,
            ]
        ]);

        if ($response->failed()) {
            throw new Exception("Gemini API request failed: " . $response->body(), $response->status());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new Exception("Gemini API returned an empty response: " . json_encode($data));
        }

        $promptTokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $completionTokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

        return [
            'text' => trim($text),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'model' => $this->model,
            'provider' => 'gemini',
            'cost' => 0.0000, // Gemini free tier cost is 0
        ];
    }
}
