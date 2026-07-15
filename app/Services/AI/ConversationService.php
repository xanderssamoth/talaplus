<?php

namespace App\Services\AI;

use App\Models\AI\AiConversation;
use App\Models\User;
use Illuminate\Support\Collection;

class ConversationService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, string $title, string $assistant, ?string $systemPrompt = null, array $attributes = []): AiConversation
    {
        return AiConversation::create(array_merge($attributes, [
            'title' => $title,
            'assistant' => $assistant,
            'system_prompt' => $systemPrompt,
            'user_id' => $user->id,
        ]));
    }

    public function find(int $conversationId): ?AiConversation
    {
        return AiConversation::query()->find($conversationId);
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
        return AiConversation::query()->findOrFail($conversationId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AiConversation>
     */
    public function getAll(User $user, array $filters = []): Collection
    {
        return AiConversation::query()
            ->whereBelongsTo($user)
            ->when(
                $filters['archived'] ?? false,
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at')
            )
            ->latest('last_message_at')
            ->latest('id')
            ->get();
    }

    public function rename(AiConversation $conversation, string $title): AiConversation
    {
        $conversation->forceFill(['title' => $title])->save();

        return $conversation->refresh();
    }

    public function archive(AiConversation $conversation): AiConversation
    {
        $conversation->forceFill(['archived_at' => now()])->save();

        return $conversation->refresh();
    }

    public function delete(AiConversation $conversation): bool
    {
        return (bool) $conversation->delete();
    }

    public function touchLastMessage(AiConversation $conversation): AiConversation
    {
        $conversation->forceFill(['last_message_at' => now()])->save();

        return $conversation->refresh();
    }
}
