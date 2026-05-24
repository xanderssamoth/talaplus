<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends SqlModel
{
    protected function tableName(): string
    {
        return 'reports';
    }

    protected function fillableAttributes(): array
    {
        return ['entity', 'entity_id', 'report_content', 'muted', 'for_user_id', 'reason_id', 'user_id'];
    }

    protected function castsAttributes(): array
    {
        return [
            'entity_id' => 'integer',
            'muted' => 'boolean',
        ];
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(Reason::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
