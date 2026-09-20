<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftTransaction extends SqlModel
{
    protected function tableName(): string
    {
        return 'gift_transactions';
    }

    protected function castsAttributes(): array
    {
        return [
            'quantity' => 'integer',
            'coins_amount' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(Pricing::class);
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(History::class);
    }
}
