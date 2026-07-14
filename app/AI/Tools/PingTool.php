<?php

namespace App\AI\Tools;

use App\Contracts\AI\Tool;
use App\Data\AI\ToolCallData;
use App\Data\AI\ToolResultData;
use App\Models\AI\AiConversation;

class PingTool implements Tool
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'ping',
            'description' => 'Teste que le système d’outils fonctionne.',
            'parameters' => [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ],
        ];
    }

    public function execute(ToolCallData $toolCall, AiConversation $conversation): ToolResultData
    {
        return new ToolResultData(
            toolCallId: $toolCall->id,
            toolName: 'ping',
            result: [
                'message' => 'pong',
            ],
        );
    }
}
