<?php

namespace App\Mappers\AI;

use App\Data\AI\AIMessageData;
use App\Data\AI\AIResponse;
use App\Data\AI\ToolCallData;

class OpenAIMessageMapper
{
    /**
     * @param array<int, AIMessageData> $messages
     * @return array<int, array<string, mixed>>
     */
    public function toOpenAI(array $messages): array
    {
        return array_map(function (AIMessageData $message): array {

            $payload = [
                'role' => $message->role,
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $message->content,
                    ],
                ],
            ];

            if ($message->name !== null) {
                $payload['name'] = $message->name;
            }

            if ($message->toolCallId !== null) {
                $payload['tool_call_id'] = $message->toolCallId;
            }

            if ($message->toolCalls !== []) {
                $payload['tool_calls'] = array_map(
                    fn (ToolCallData $toolCall) => $this->toolCallToOpenAI($toolCall),
                    $message->toolCalls
                );
            }

            if ($message->attachments !== []) {
                $payload['attachments'] = $message->attachments;
            }

            return $payload;

        }, $messages);
    }

    public function toAIResponse(mixed $response): AIResponse
    {
        return new AIResponse(

            content: $response->outputText ?? '',

            model: $response->model ?? '',

            promptTokens: $response->usage->inputTokens ?? 0,

            completionTokens: $response->usage->outputTokens ?? 0,

            totalTokens: $response->usage->totalTokens ?? 0,

            toolCalls: [],

            role: 'assistant',

            provider: 'openai',

            responseId: $response->id ?? null,

            finishReason: $response->status ?? null,

            error: null,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function toolCallToOpenAI(ToolCallData $toolCall): array
    {
        return [

            'type' => 'function',

            'name' => $toolCall->name,

            'parameters' => $toolCall->arguments,

        ];
    }

    /**
     * @return array<int, ToolCallData>
     */
    private function toolCallsFromResponse(mixed $response): array
    {
        return [];
    }
}