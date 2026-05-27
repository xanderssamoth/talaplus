<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends SqlModel
{
    protected function tableName(): string
    {
        return 'payments';
    }

    protected function castsAttributes(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_customer' => 'decimal:2',
            'type' => 'integer',
            'status' => 'integer',
            'entity_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
