<?php

namespace App\Services\AI;

use App\Models\AI\AiConversation;
use App\Models\AI\AiMessage;
use Illuminate\Support\Collection;
use RuntimeException;

class MessageService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(AiConversation $conversation, string $role, string $content, array $metadata = []): AiMessage
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function storeUserMessage(AiConversation $conversation, string $content, array $metadata = []): AiMessage
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function storeAssistantMessage(AiConversation $conversation, string $content, array $metadata = []): AiMessage
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * @param  array<int, mixed>  $files
     */
    public function attachFiles(AiMessage $message, array $files = []): AiMessage
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getConversationMessages(AiConversation $conversation, array $filters = []): Collection
    {
        return collect();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function updateMetadata(AiMessage $message, array $metadata = []): AiMessage
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * Retourne les messages d'une conversation dans leur ordre chronologique.
     *
     * @return \Illuminate\Support\Collection<int, AiMessage>
     */
    public function history(AiConversation $conversation): Collection
    {
        return $conversation->messages()->orderBy('id')->get();
    }
}
