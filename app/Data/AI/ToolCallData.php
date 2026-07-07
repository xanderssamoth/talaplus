<?php

namespace App\Data\AI;

class ToolCallData
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments = [],
        public readonly string $type = 'function',
    ) {}
}
