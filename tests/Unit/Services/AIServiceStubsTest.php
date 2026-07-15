<?php

namespace Tests\Unit\Services;

use App\Contracts\AI\AIProvider;
use App\Data\AI\AIResponse;
use App\Data\AI\ChatRequestData;
use App\Data\AI\ToolCallData;
use App\Data\AI\ToolResultData;
use App\Mappers\AI\OpenAIMessageMapper;
use App\Models\AI\AiConversation;
use App\Models\AI\AiMessage;
use App\Models\User;
use App\Services\AI\AIConversationRunner;
use App\Services\AI\AIService;
use App\Services\AI\AISettingService;
use App\Services\AI\ConversationService;
use App\Services\AI\MessageService;
use App\Services\AI\OpenAIService;
use App\Services\AI\PromptService;
use App\Services\AI\ToolService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

class AIServiceStubsTest extends TestCase
{
    public function test_ai_service_stub_signatures_are_available(): void
    {
        $this->assertContains(AIProvider::class, class_implements(OpenAIService::class));

        $this->assertConstructorSignature(AIService::class, [
            ConversationService::class,
            AIConversationRunner::class,
        ]);

        $this->assertConstructorSignature(AIConversationRunner::class, [
            ConversationService::class,
            MessageService::class,
            PromptService::class,
            AIProvider::class,
            ToolService::class,
        ]);

        $this->assertConstructorSignature(OpenAIService::class, [
            AISettingService::class,
            ToolService::class,
            OpenAIMessageMapper::class,
        ]);

        $this->assertMethodSignature(AIService::class, 'chat', AiMessage::class, [
            User::class,
            ChatRequestData::class,
        ]);
        $this->assertMethodSignature(AIService::class, 'createConversation', AiConversation::class, [
            User::class,
            'string',
            '?string',
            '?string',
            'array',
        ]);
        $this->assertMethodSignature(AIService::class, 'getConversation', '?'.AiConversation::class, ['int']);
        $this->assertMethodSignature(AIService::class, 'getConversations', Collection::class, [User::class, 'array']);
        $this->assertMethodSignature(AIService::class, 'deleteConversation', 'bool', [AiConversation::class]);
        $this->assertMethodSignature(AIService::class, 'streamMessage', 'iterable', [
            User::class,
            'string',
            '?'.AiConversation::class,
            'array',
        ]);
        $this->assertMethodSignature(AIService::class, 'regenerateResponse', AiMessage::class, [
            AiConversation::class,
            AiMessage::class,
            'array',
        ]);

        $this->assertMethodSignature(OpenAIService::class, 'chat', AIResponse::class, ['array', 'array']);
        $this->assertMethodSignature(OpenAIService::class, 'stream', 'iterable', ['array', 'array']);
        $this->assertMethodSignature(OpenAIService::class, 'embeddings', 'array', ['array', 'array']);
        $this->assertMethodSignature(OpenAIService::class, 'isAvailable', 'array', []);

        $this->assertMethodSignature(AIProvider::class, 'chat', AIResponse::class, ['array', 'array']);
        $this->assertMethodSignature(AIProvider::class, 'stream', 'iterable', ['array', 'array']);
        $this->assertMethodSignature(AIProvider::class, 'embeddings', 'array', ['array', 'array']);
        $this->assertMethodSignature(AIProvider::class, 'isAvailable', 'array', []);

        $this->assertMethodSignature(PromptService::class, 'buildSystemPrompt', 'string', ['?'.User::class, 'array']);
        $this->assertMethodSignature(PromptService::class, 'buildDeveloperPrompt', 'string', ['?'.User::class, 'array']);
        $this->assertMethodSignature(PromptService::class, 'buildMessages', 'array', [
            AiConversation::class,
            Collection::class,
            'array',
        ]);
        $this->assertParameterNames(PromptService::class, 'buildMessages', [
            'conversation',
            'messages',
            'context',
        ]);

        $this->assertMethodSignature(ConversationService::class, 'create', AiConversation::class, [
            User::class,
            'string',
            'string',
            '?string',
            'array',
        ]);
        $this->assertMethodSignature(ConversationService::class, 'find', '?'.AiConversation::class, ['int']);
        $this->assertMethodSignature(ConversationService::class, 'findForUser', '?'.AiConversation::class, [
            User::class,
            'int',
        ]);
        $this->assertMethodSignature(ConversationService::class, 'findOrFail', AiConversation::class, ['int']);
        $this->assertMethodSignature(ConversationService::class, 'getAll', Collection::class, [User::class, 'array']);
        $this->assertMethodSignature(ConversationService::class, 'rename', AiConversation::class, [
            AiConversation::class,
            'string',
        ]);
        $this->assertMethodSignature(ConversationService::class, 'archive', AiConversation::class, [AiConversation::class]);
        $this->assertMethodSignature(ConversationService::class, 'delete', 'bool', [AiConversation::class]);
        $this->assertMethodSignature(ConversationService::class, 'touchLastMessage', AiConversation::class, [
            AiConversation::class,
        ]);

        $this->assertMethodSignature(MessageService::class, 'store', AiMessage::class, [
            AiConversation::class,
            'string',
            'string',
            'array',
        ]);
        $this->assertMethodSignature(MessageService::class, 'storeUserMessage', AiMessage::class, [
            AiConversation::class,
            'string',
            'array',
        ]);
        $this->assertMethodSignature(MessageService::class, 'storeAssistantMessage', AiMessage::class, [
            AiConversation::class,
            'string',
            'array',
        ]);
        $this->assertMethodSignature(MessageService::class, 'attachFiles', AiMessage::class, [
            AiMessage::class,
            'array',
        ]);
        $this->assertMethodSignature(MessageService::class, 'getConversationMessages', Collection::class, [
            AiConversation::class,
            'array',
        ]);
        $this->assertMethodSignature(MessageService::class, 'updateMetadata', AiMessage::class, [
            AiMessage::class,
            'array',
        ]);

        $this->assertMethodSignature(ToolService::class, 'execute', ToolResultData::class, [
            ToolCallData::class,
            AiConversation::class,
        ]);
        $this->assertMethodSignature(ToolService::class, 'hasTool', 'bool', ['string']);
        $this->assertMethodSignature(ToolService::class, 'listTools', 'array', []);
        $this->assertMethodSignature(ToolService::class, 'getToolDefinitions', 'array', []);

        $this->assertMethodSignature(AISettingService::class, 'getProvider', '?string', []);
        $this->assertMethodSignature(AISettingService::class, 'getModel', '?string', []);
        $this->assertMethodSignature(AISettingService::class, 'getTemperature', '?float', []);
        $this->assertMethodSignature(AISettingService::class, 'getMaxTokens', '?int', []);
        $this->assertMethodSignature(AISettingService::class, 'isStreamingEnabled', 'bool', []);
        $this->assertMethodSignature(AISettingService::class, 'getConfiguration', 'array', []);
    }

    public function test_ai_setting_configuration_returns_expected_shape(): void
    {
        $configuration = (new AISettingService)->getConfiguration();

        $this->assertSame([
            'provider' => 'openai',
            'model' => env('OPENAI_MODEL', 'gpt-5.5'),
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'stream' => false,
        ], $configuration);
    }

    /**
     * @param  class-string  $class
     * @param  array<int, string>  $parameters
     */
    private function assertConstructorSignature(string $class, array $parameters): void
    {
        $reflection = new ReflectionMethod($class, '__construct');

        $this->assertSame($parameters, array_map(
            fn ($parameter): string => $this->typeName($parameter->getType()),
            $reflection->getParameters()
        ));
    }

    /**
     * @param  class-string  $class
     * @param  array<int, string>  $parameters
     */
    private function assertParameterNames(string $class, string $method, array $parameters): void
    {
        $reflection = new ReflectionMethod($class, $method);

        $this->assertSame($parameters, array_map(
            fn ($parameter): string => $parameter->getName(),
            $reflection->getParameters()
        ));
    }

    /**
     * @param  class-string  $class
     * @param  array<int, string>  $parameters
     */
    private function assertMethodSignature(string $class, string $method, string $returnType, array $parameters): void
    {
        $reflection = new ReflectionMethod($class, $method);

        $this->assertSame($returnType, $this->typeName($reflection->getReturnType()));
        $this->assertSame($parameters, array_map(
            fn ($parameter): string => $this->typeName($parameter->getType()),
            $reflection->getParameters()
        ));
    }

    private function typeName(?ReflectionNamedType $type): string
    {
        $this->assertNotNull($type);

        return ($type->allowsNull() ? '?' : '').$type->getName();
    }
}
