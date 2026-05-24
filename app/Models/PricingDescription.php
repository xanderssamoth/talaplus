<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class PricingDescription extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['description_title', 'description_content'];

    protected function tableName(): string
    {
        return 'pricing_descriptions';
    }

    protected function fillableAttributes(): array
    {
        return ['description_title', 'description_content', 'pricing_id'];
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(Pricing::class);
    }
}
