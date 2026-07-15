<?php

namespace App\Services\AI;

use App\Contracts\AI\AIProvider;
use App\Data\AI\AIResponse;
use App\Data\AI\ChatRequestData;
use App\Models\AI\AiConversation;
use App\Models\AI\AiMessage;
use App\Models\User;
use RuntimeException;

class AIConversationRunner
{
    public function __construct(
        private ConversationService $conversationService,
        private MessageService $messageService,
        private PromptService $promptService,
        private AIProvider $provider,
        private ToolService $toolService,
    ) {}

    public function run(User $user, ChatRequestData $request): AiMessage
    {
        $conversation = $this->resolveConversation($user, $request);

        $this->storeUserMessage($conversation, $request);

        $response = $this->callModel($conversation, $request);
        $response = $this->handleToolCalls($response, $conversation);

        return $this->storeAssistantMessage($conversation, $response);
    }

    public function stream(User $user, string $content, ?AiConversation $conversation = null, array $options = []): iterable
    {
        throw new RuntimeException('Not implemented.');
    }

    private function resolveConversation(User $user, ChatRequestData $request): AiConversation
    {
        if ($request->conversationId !== null) {
            $conversation = $this->conversationService->findForUser(
                $user,
                $request->conversationId
            );

            if ($conversation === null) {
                throw new RuntimeException('Conversation introuvable.');
            }

            return $conversation;
        }

        $assistant = $request->assistant ?? 'default';
        $systemPrompt = $this->promptService->buildSystemPrompt($user, $request->context);
        $title = $request->title;

        if ($title === null || trim($title) === '') {
            $title = mb_substr($request->message, 0, 60);
        }

        return $this->conversationService->create(
            user: $user,
            title: $title,
            assistant: $assistant,
            systemPrompt: $systemPrompt
        );
    }

    private function storeUserMessage(AiConversation $conversation, ChatRequestData $request): AiMessage
    {
        $message = $this->messageService->storeUserMessage(
            conversation: $conversation,
            content: $request->message,
            metadata: [
                'source' => 'user',
                'context' => $request->context,
                'options' => $request->options,
            ]
        );

        $this->conversationService->touchLastMessage($conversation);

        return $message;
    }

    private function callModel(AiConversation $conversation, ChatRequestData $request): AIResponse
    {
        $messages = $this->messageService
            ->getConversationMessages($conversation);

        $payload = $this->promptService
            ->buildMessages(
                $conversation,
                $messages,
                $request->context
            );

        return $this->provider->chat(
            $payload,
            $request->options
        );
    }

    private function handleToolCalls(AIResponse $response, AiConversation $conversation): AIResponse
    {
        return $response;
    }

    private function storeAssistantMessage(AiConversation $conversation, AIResponse $response): AiMessage
    {
        $message = $this->messageService->storeAssistantMessage(
            conversation: $conversation,
            content: $response->content,
            metadata: [
                'provider' => $response->provider,
                'model' => $response->model,
                'response_id' => $response->responseId,
                'finish_reason' => $response->finishReason,

                'usage' => [
                    'prompt_tokens' => $response->promptTokens,
                    'completion_tokens' => $response->completionTokens,
                    'total_tokens' => $response->totalTokens,
                ],

                'tool_calls' => array_map(
                    fn ($tool) => [
                        'id' => $tool->id,
                        'name' => $tool->name,
                        'arguments' => $tool->arguments,
                    ],
                    $response->toolCalls
                ),
            ]
        );

        $this->conversationService->touchLastMessage($conversation);

        return $message;
    }
}
