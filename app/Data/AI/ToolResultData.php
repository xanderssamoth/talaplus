<?php

namespace App\Data\AI;

class ToolResultData
{
    /**
     * @param array<string, mixed> $result
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly array $result = [],
        public readonly bool $success = true,
        public readonly ?string $error = null,
    ) {}
}
