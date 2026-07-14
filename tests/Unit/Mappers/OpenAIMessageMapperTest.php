<?php

namespace Tests\Unit\Mappers;

use App\Data\AI\AIMessageData;
use App\Data\AI\ToolCallData;
use App\Mappers\AI\OpenAIMessageMapper;
use PHPUnit\Framework\TestCase;

class OpenAIMessageMapperTest extends TestCase
{
    public function test_to_open_ai_maps_message_data_to_provider_payload(): void
    {
        $mapper = new OpenAIMessageMapper;

        $payload = $mapper->toOpenAI([
            new AIMessageData(
                role: 'assistant',
                content: 'Searching...',
                toolCalls: [
                    new ToolCallData(
                        id: 'call_123',
                        name: 'search_videos',
                        arguments: ['query' => 'music'],
                    ),
                ],
                attachments: ['file_123'],
                name: 'assistant',
                toolCallId: 'tool_123',
            ),
        ]);

        $this->assertSame('assistant', $payload[0]['role']);
        $this->assertSame('input_text', $payload[0]['content'][0]['type']);
        $this->assertSame('Searching...', $payload[0]['content'][0]['text']);
        $this->assertSame('assistant', $payload[0]['name']);
        $this->assertSame('tool_123', $payload[0]['tool_call_id']);
        $this->assertSame(['file_123'], $payload[0]['attachments']);
        $this->assertSame('function', $payload[0]['tool_calls'][0]['type']);
        $this->assertSame('search_videos', $payload[0]['tool_calls'][0]['name']);
        $this->assertSame(['query' => 'music'], $payload[0]['tool_calls'][0]['parameters']);
    }

    public function test_to_ai_response_maps_open_ai_chat_response_to_ai_response(): void
    {
        $mapper = new OpenAIMessageMapper;

        $response = $mapper->toAIResponse([
            'id' => 'chatcmpl_123',
            'model' => 'gpt-test',
            'choices' => [
                [
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'I will search.',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'search_videos',
                                    'arguments' => '{"query":"music"}',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 11,
                'completion_tokens' => 7,
                'total_tokens' => 18,
            ],
        ]);

        $this->assertSame('I will search.', $response->content);
        $this->assertSame('gpt-test', $response->model);
        $this->assertSame(11, $response->promptTokens);
        $this->assertSame(7, $response->completionTokens);
        $this->assertSame(18, $response->totalTokens);
        $this->assertSame('assistant', $response->role);
        $this->assertSame('openai', $response->provider);
        $this->assertSame('chatcmpl_123', $response->responseId);
        $this->assertSame('tool_calls', $response->finishReason);
        $this->assertSame('search_videos', $response->toolCalls[0]->name);
        $this->assertSame(['query' => 'music'], $response->toolCalls[0]->arguments);
    }

    public function test_to_ai_response_maps_open_ai_error_message(): void
    {
        $mapper = new OpenAIMessageMapper;

        $response = $mapper->toAIResponse([
            'error' => [
                'message' => 'Rate limit exceeded',
            ],
        ]);

        $this->assertSame('Rate limit exceeded', $response->error);
        $this->assertSame('', $response->content);
    }
}
