<?php

namespace App\Services\AI;

use App\Data\AI\ChatRequestData;
use App\Models\AI\AiConversation;
use App\Models\AI\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class AIService
{
    public function __construct(
        private ConversationService $conversationService,
        private AIConversationRunner $conversationRunner,
    ) {}

    public function chat(User $user, ChatRequestData $request): AiMessage
    {
        return $this->conversationRunner->run($user, $request);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createConversation(User $user, string $title, ?string $assistant = null, ?string $systemPrompt = null, array $attributes = []): AiConversation
    {
        return $this->conversationService->create(
            user: $user,
            title: $title,
            assistant: $assistant ?? 'default',
            systemPrompt: $systemPrompt,
            attributes: $attributes
        );
    }

    public function getConversation(int $conversationId): ?AiConversation
    {
        return $this->conversationService->find($conversationId);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getConversations(User $user, array $filters = []): Collection
    {
        return $this->conversationService->getAll($user, $filters);
    }

    public function deleteConversation(AiConversation $conversation): bool
    {
        return $this->conversationService->delete($conversation);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return iterable<int, mixed>
     */
    public function streamMessage(User $user, string $content, ?AiConversation $conversation = null, array $options = []): iterable
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function regenerateResponse(AiConversation $conversation, AiMessage $message, array $options = []): AiMessage
    {
        throw new RuntimeException('Not implemented.');
    }
}
