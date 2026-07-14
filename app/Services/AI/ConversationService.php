<?php

namespace App\Services\AI;

use App\Models\AI\AiConversation;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class ConversationService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, string $title, string $assistant, ?string $systemPrompt = null, array $attributes = []): AiConversation
    {
        throw new RuntimeException('Not implemented.');
    }

    public function find(int $conversationId): ?AiConversation
    {
        return null;
    }

    public function findForUser(User $user, int $conversationId): ?AiConversation
    {
        return AiConversation::query()
            ->whereBelongsTo($user)
            ->whereKey($conversationId)
            ->first();
    }

    public function findOrFail(int $conversationId): AiConversation
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(User $user, array $filters = []): Collection
    {
        return collect();
    }

    public function rename(AiConversation $conversation, string $title): AiConversation
    {
        throw new RuntimeException('Not implemented.');
    }

    public function archive(AiConversation $conversation): AiConversation
    {
        throw new RuntimeException('Not implemented.');
    }

    public function delete(AiConversation $conversation): bool
    {
        return false;
    }

    public function touchLastMessage(AiConversation $conversation): AiConversation
    {
        throw new RuntimeException('Not implemented.');
    }
}
