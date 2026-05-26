<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reaction extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'reactions';
    }

    protected function fillableAttributes(): array
    {
        return ['type', 'number_of_stars', 'pricing_id', 'media_id', 'product_id', 'user_id'];
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(Pricing::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
