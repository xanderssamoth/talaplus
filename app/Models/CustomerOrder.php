<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerOrder extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'customer_orders';
    }

    protected function castsAttributes(): array
    {
        return [
            'price_at_that_time' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
