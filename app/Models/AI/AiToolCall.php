<?php

namespace App\Models\AI;

use App\Models\SqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends SqlModel
{
    protected function tableName(): string
    {
        return 'ai_tool_calls';
    }

    /**
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [
            'arguments' => 'array',
            'response' => 'array',
        ];
    }

    public function aiMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }
}
