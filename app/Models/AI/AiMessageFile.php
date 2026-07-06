<?php

namespace App\Models\AI;

use App\Models\File;
use App\Models\SqlModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessageFile extends SqlModel
{
    protected function tableName(): string
    {
        return 'ai_message_files';
    }

    public function aiMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
