<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaProgress extends SqlModel
{
    protected function tableName(): string
    {
        return 'media_progresses';
    }

    protected function castsAttributes(): array
    {
        return [
            'watched_seconds' => 'integer',
            'percentage' => 'decimal:2',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
