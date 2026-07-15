<?php

namespace App\Services\AI;

use App\Data\AI\AIMessageData;
use App\Data\AI\ToolResultData;
use App\Models\AI\AiConversation;
use App\Models\User;
use Illuminate\Support\Collection;

class PromptService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function buildSystemPrompt(?User $user = null, array $context = []): string
    {
        return (string) ($context['system_prompt'] ?? 'Tu es l assistant IA de TALA+.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function buildDeveloperPrompt(?User $user = null, array $context = []): string
    {
        return (string) ($context['developer_prompt'] ?? 'Reponds de facon claire et concise.');
    }

    /**
     * @param  array<int, ToolResultData>  $results
     * @return array<int, AIMessageData>
     */
    public function buildToolMessages(array $results): array
    {
        return array_map(
            function (ToolResultData $result): AIMessageData {

                return new AIMessageData(
                    role: 'tool',
                    content: json_encode(
                        [
                            'success' => $result->success,
                            'result' => $result->result,
                            'error' => $result->error,
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    toolCallId: $result->toolCallId,
                );
            },
            $results
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, AIMessageData>
     */
    public function buildMessages(AiConversation $conversation, Collection $messages, array $context = []): array
    {
        $payload = [];

        if ($conversation->system_prompt !== null && trim((string) $conversation->system_prompt) !== '') {
            $payload[] = new AIMessageData(
                role: 'system',
                content: (string) $conversation->system_prompt,
            );
        }

        $developerPrompt = $this->buildDeveloperPrompt(context: $context);

        if (trim($developerPrompt) !== '') {
            $payload[] = new AIMessageData(
                role: 'developer',
                content: $developerPrompt,
            );
        }

        foreach ($messages as $message) {
            $payload[] = new AIMessageData(
                role: (string) $message->role,
                content: (string) $message->content,
            );
        }

        return $payload;
    }
}
