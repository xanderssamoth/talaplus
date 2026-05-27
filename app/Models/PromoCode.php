<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends SqlModel
{
    protected function tableName(): string
    {
        return 'promo_codes';
    }

    protected function castsAttributes(): array
    {
        return [
            'validity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
