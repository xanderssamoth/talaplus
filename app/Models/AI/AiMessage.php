<?php

namespace App\Models\AI;

use App\Models\File;
use App\Models\SqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends SqlModel
{
    protected function tableName(): string
    {
        return 'ai_messages';
    }

    /**
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'response_time_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function messageFiles(): HasMany
    {
        return $this->hasMany(AiMessageFile::class, 'ai_message_id');
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'ai_message_files', 'ai_message_id', 'file_id')->withTimestamps();
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class, 'ai_message_id');
    }
}
