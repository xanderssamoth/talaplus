<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends SqlModel
{
    protected function tableName(): string
    {
        return 'payments';
    }

    protected function fillableAttributes(): array
    {
        return [
            'reference',
            'provider_reference',
            'order_number',
            'amount',
            'amount_customer',
            'phone',
            'currency',
            'channel',
            'type',
            'status',
            'reason',
            'entity',
            'entity_id',
            'user_id',
        ];
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
