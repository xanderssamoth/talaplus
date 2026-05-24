<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class History extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'histories';
    }

    protected function fillableAttributes(): array
    {
        return ['word', 'entity', 'entity_id', 'action', 'user_id'];
    }

    protected function castsAttributes(): array
    {
        return [
            'entity_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
