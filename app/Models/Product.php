<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'products';
    }

    protected function fillableAttributes(): array
    {
        return [
            'product_name',
            'product_description',
            'type',
            'quantity',
            'price',
            'currency',
            'action',
            'is_shared',
            'category_id',
        ];
    }

    protected function castsAttributes(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'is_shared' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
