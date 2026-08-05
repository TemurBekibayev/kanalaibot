<?php

namespace App\Services\Ai\Contracts;

interface AiProviderInterface
{
    /**
     * Generate a post content based on prompt instructions.
     *
     * @param string $prompt
     * @param array $options
     * @return array{
     *     text: string,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     model: string,
     *     provider: string,
     *     cost: float
     * }
     */
    public function generatePost(string $prompt, array $options = []): array;
}
