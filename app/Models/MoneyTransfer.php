<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneyTransfer extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'money_transfers';
    }

    protected function fillableAttributes(): array
    {
        return ['has_commission', 'commission_amount', 'status', 'payment_id'];
    }

    protected function castsAttributes(): array
    {
        return [
            'has_commission' => 'boolean',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
