<?php

namespace App\Data\AI;

class AIMessageData
{
    /**
     * @param  array<int, ToolCallData>  $toolCalls
     * @param  array<int, mixed>  $attachments
     */
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly array $attachments = [],
        public readonly ?string $name = null,
        public readonly ?string $toolCallId = null,
    ) {}
}
