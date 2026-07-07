<?php

namespace Tests\Unit\Data;

use App\Data\AI\AIMessageData;
use App\Data\AI\AIResponse;
use App\Data\AI\ToolCallData;
use PHPUnit\Framework\TestCase;

class AIDataTest extends TestCase
{
    public function test_ai_response_exposes_provider_agnostic_data(): void
    {
        $toolCall = new ToolCallData(
            id: 'call_123',
            name: 'search_videos',
            arguments: ['query' => 'music'],
        );

        $response = new AIResponse(
            content: 'Hello',
            model: 'gpt-test',
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15,
            toolCalls: [$toolCall],
            role: 'assistant',
            provider: 'openai',
            responseId: 'response_123',
            finishReason: 'stop',
            error: null,
        );

        $this->assertSame('Hello', $response->content);
        $this->assertSame('gpt-test', $response->model);
        $this->assertSame(15, $response->totalTokens);
        $this->assertSame('assistant', $response->role);
        $this->assertSame('openai', $response->provider);
        $this->assertSame('search_videos', $response->toolCalls[0]->name);
        $this->assertSame('response_123', $response->responseId);
        $this->assertSame('stop', $response->finishReason);
        $this->assertNull($response->error);
    }

    public function test_ai_message_data_exposes_message_payload(): void
    {
        $message = new AIMessageData(
            role: 'user',
            content: 'Bonjour',
            attachments: ['file_123'],
            name: 'member',
            toolCallId: 'call_123',
        );

        $this->assertSame('user', $message->role);
        $this->assertSame('Bonjour', $message->content);
        $this->assertSame(['file_123'], $message->attachments);
        $this->assertSame('member', $message->name);
        $this->assertSame('call_123', $message->toolCallId);
    }
}
