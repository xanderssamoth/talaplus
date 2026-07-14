<?php

namespace App\Data\AI;

class ChatRequestData
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $message,
        public readonly ?int $conversationId = null,
        public readonly ?string $assistant = null,
        public readonly ?string $title = null,
        public readonly array $context = [],
        public readonly array $options = [],
    ) {}
}
