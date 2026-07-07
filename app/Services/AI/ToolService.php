<?php

namespace App\Services\AI;

class ToolService
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    public function execute(string $toolName, array $arguments = []): ?array
    {
        return null;
    }

    public function hasTool(string $toolName): bool
    {
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTools(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(): array
    {
        return [];
    }
}
