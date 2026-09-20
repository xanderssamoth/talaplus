<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends SqlModel
{
    protected function tableName(): string
    {
        return 'wallets';
    }

    protected function castsAttributes(): array
    {
        return [
            'coins_balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
