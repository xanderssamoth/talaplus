<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Pricing extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['pricing_name'];

    protected function tableName(): string
    {
        return 'pricings';
    }

    protected function fillableAttributes(): array
    {
        return ['pricing_name', 'pricing_type', 'reason', 'pricing_cost', 'currency'];
    }

    protected function castsAttributes(): array
    {
        return [
            'pricing_cost' => 'decimal:2',
        ];
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(PricingDescription::class);
    }
}
