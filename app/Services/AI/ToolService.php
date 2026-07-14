<?php

namespace App\Services\AI;

use App\Data\AI\ToolCallData;
use App\Data\AI\ToolResultData;
use App\Models\AI\AiConversation;

class ToolService
{
    /**
     * @param  \App\Data\AI\ToolCallData  $toolCall
     * @param  \App\Models\AI\AiConversation  $conversation
     * @return \App\Data\AI\ToolResultData
     */
    public function execute(ToolCallData $toolCall, AiConversation $conversation): ToolResultData
    {
        return match ($toolCall->name) {
            'ping' => new ToolResultData(
                toolCallId: $toolCall->id,
                toolName: 'ping',
                result: [
                    'message' => 'pong',
                ],
            ),

            default => new ToolResultData(
                toolCallId: $toolCall->id,
                toolName: $toolCall->name,
                success: false,
                error: 'Unknown tool.',
            ),
        };
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
