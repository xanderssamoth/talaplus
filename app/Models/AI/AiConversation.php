<?php

namespace App\Models\AI;

use App\Models\SqlModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiConversation extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'ai_conversations';
    }

    /**
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }
}
