<?php

namespace App\Data\AI;

class AIResponse
{
    /**
     * @param  array<int, ToolCallData>  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly array $toolCalls = [],
        public readonly string $role = 'assistant',
        public readonly ?string $provider = 'openai',
        public readonly ?string $responseId = null,
        public readonly ?string $finishReason = null,
        public readonly ?string $error = null,
    ) {}
}
