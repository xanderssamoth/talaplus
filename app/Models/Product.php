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

    protected function castsAttributes(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'is_shared' => 'boolean',
            'price_reduction_start' => 'datetime',
            'price_reduction_end' => 'datetime',
            'reduction_rate' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(Specification::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }
}
