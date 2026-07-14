<?php

namespace App\Mappers\AI;

use App\Data\AI\AIMessageData;
use App\Data\AI\AIResponse;
use App\Data\AI\ToolCallData;

class OpenAIMessageMapper
{
    /**
     * @param  array<int, AIMessageData>  $messages
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

            content: (string) ($this->value($response, 'outputText')
                ?? $this->value($response, 'output_text')
                ?? $this->value($response, 'choices.0.message.content')
                ?? ''),

            model: (string) ($this->value($response, 'model') ?? ''),

            promptTokens: (int) ($this->value($response, 'usage.inputTokens')
                ?? $this->value($response, 'usage.prompt_tokens')
                ?? $this->value($response, 'usage.input_tokens')
                ?? 0),

            completionTokens: (int) ($this->value($response, 'usage.outputTokens')
                ?? $this->value($response, 'usage.completion_tokens')
                ?? $this->value($response, 'usage.output_tokens')
                ?? 0),

            totalTokens: (int) ($this->value($response, 'usage.totalTokens')
                ?? $this->value($response, 'usage.total_tokens')
                ?? 0),

            toolCalls: $this->toolCallsFromResponse($this->value($response, 'choices.0.message', [])),

            role: (string) ($this->value($response, 'choices.0.message.role') ?? 'assistant'),

            provider: 'openai',

            responseId: $this->nullableString($this->value($response, 'id')),

            finishReason: $this->nullableString($this->value($response, 'status')
                ?? $this->value($response, 'choices.0.finish_reason')),

            error: $this->nullableString($this->value($response, 'error.message')),
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
        $toolCalls = $this->value($response, 'tool_calls', []);

        if (! is_array($toolCalls)) {
            return [];
        }

        return array_map(function (mixed $toolCall): ToolCallData {
            $arguments = $this->value($toolCall, 'function.arguments', []);

            if (is_string($arguments)) {
                $decodedArguments = json_decode($arguments, true);
                $arguments = is_array($decodedArguments) ? $decodedArguments : [];
            }

            return new ToolCallData(
                id: (string) ($this->value($toolCall, 'id') ?? ''),
                name: (string) ($this->value($toolCall, 'function.name') ?? ''),
                arguments: is_array($arguments) ? $arguments : [],
                type: (string) ($this->value($toolCall, 'type') ?? 'function'),
            );
        }, $toolCalls);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function value(mixed $source, string $path, mixed $default = null): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (is_array($source) && array_key_exists($segment, $source)) {
                $source = $source[$segment];

                continue;
            }

            if (is_object($source) && isset($source->{$segment})) {
                $source = $source->{$segment};

                continue;
            }

            return $default;
        }

        return $source;
    }
}
