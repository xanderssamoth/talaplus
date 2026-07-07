<?php

namespace App\Services\AI;

use App\Data\AI\AIMessageData;
use App\Models\AI\AiConversation;
use App\Models\User;

class PromptService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function buildSystemPrompt(?User $user = null, array $context = []): string
    {
        return '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function buildDeveloperPrompt(?User $user = null, array $context = []): string
    {
        return '';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, AIMessageData>
     */
    public function buildMessages(AiConversation $conversation, ?string $newUserMessage = null, array $context = []): array
    {
        return [];
    }
}
