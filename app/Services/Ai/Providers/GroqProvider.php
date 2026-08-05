<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class GroqProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key', env('GROQ_API_KEY'));
        $this->model = config('services.groq.model', 'llama-3.1-8b-instant'); // Configurable default
    }

    public function generatePost(string $prompt, array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new Exception("Groq API key is not configured.");
        }

        $url = "https://api.groq.com/openai/v1/chat/completions";

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000,
        ]);

        if ($response->failed()) {
            throw new Exception("Groq API request failed: " . $response->body(), $response->status());
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!$text) {
            throw new Exception("Groq API returned an empty response: " . json_encode($data));
        }

        $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
        $completionTokens = $data['usage']['completion_tokens'] ?? 0;

        return [
            'text' => trim($text),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'model' => $this->model,
            'provider' => 'groq',
            'cost' => 0.0000, // Groq free tier
        ];
    }
}
