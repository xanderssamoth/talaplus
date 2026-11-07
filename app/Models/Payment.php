<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends SqlModel
{
    public const STATUS_SUCCESSFUL = 0;

    public const STATUS_FAILED = 1;

    public const STATUS_PENDING = 2;

    protected function tableName(): string
    {
        return 'payments';
    }

    protected function castsAttributes(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_customer' => 'decimal:2',
            'coins_amount' => 'integer',
            'coin_unit_price' => 'decimal:6',
            'premium_duration_months' => 'integer',
            'premium_plan_amount' => 'decimal:2',
            'premium_activated_at' => 'datetime',
            'type' => 'integer',
            'status' => 'integer',
            'entity_id' => 'integer',
            'coins_credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
