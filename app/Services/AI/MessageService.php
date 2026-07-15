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
        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'model' => $metadata['model'] ?? null,
            'prompt_tokens' => $metadata['usage']['prompt_tokens'] ?? $metadata['prompt_tokens'] ?? null,
            'completion_tokens' => $metadata['usage']['completion_tokens'] ?? $metadata['completion_tokens'] ?? null,
            'total_tokens' => $metadata['usage']['total_tokens'] ?? $metadata['total_tokens'] ?? null,
            'response_time_ms' => $metadata['response_time_ms'] ?? null,
            'error_message' => $metadata['error_message'] ?? $metadata['error'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function storeUserMessage(AiConversation $conversation, string $content, array $metadata = []): AiMessage
    {
        return $this->store($conversation, 'user', $content, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function storeAssistantMessage(AiConversation $conversation, string $content, array $metadata = []): AiMessage
    {
        return $this->store($conversation, 'assistant', $content, $metadata);
    }

    /**
     * @param  array<int, mixed>  $files
     */
    public function attachFiles(AiMessage $message, array $files = []): AiMessage
    {
        if ($files !== []) {
            throw new RuntimeException('File attachment is not implemented.');
        }

        return $message->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AiMessage>
     */
    public function getConversationMessages(AiConversation $conversation, array $filters = []): Collection
    {
        return $conversation->messages()
            ->when(isset($filters['role']), fn ($query) => $query->where('role', $filters['role']))
            ->oldest('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function updateMetadata(AiMessage $message, array $metadata = []): AiMessage
    {
        $message->forceFill([
            'model' => $metadata['model'] ?? $message->model,
            'prompt_tokens' => $metadata['usage']['prompt_tokens'] ?? $metadata['prompt_tokens'] ?? $message->prompt_tokens,
            'completion_tokens' => $metadata['usage']['completion_tokens'] ?? $metadata['completion_tokens'] ?? $message->completion_tokens,
            'total_tokens' => $metadata['usage']['total_tokens'] ?? $metadata['total_tokens'] ?? $message->total_tokens,
            'response_time_ms' => $metadata['response_time_ms'] ?? $message->response_time_ms,
            'error_message' => $metadata['error_message'] ?? $metadata['error'] ?? $message->error_message,
        ])->save();

        return $message->refresh();
    }

    /**
     * Retourne les messages d'une conversation dans leur ordre chronologique.
     *
     * @return Collection<int, AiMessage>
     */
    public function history(AiConversation $conversation): Collection
    {
        return $this->getConversationMessages($conversation);
    }
}
