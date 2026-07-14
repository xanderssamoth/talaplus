<?php

namespace App\Contracts\AI;

use App\Data\AI\ToolCallData;
use App\Data\AI\ToolResultData;
use App\Models\AI\AiConversation;

interface Tool
{
    /**
     * Définition envoyée au modèle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * Exécute l'outil.
     */
    public function execute(
        ToolCallData $toolCall,
        AiConversation $conversation,
    ): ToolResultData;
}