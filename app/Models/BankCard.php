<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankCard extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'bank_cards';
    }

    protected function castsAttributes(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    protected function hiddenAttributes(): array
    {
        return ['card_number', 'cvv_code'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
