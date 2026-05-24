<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Category extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['category_name', 'category_description'];

    protected function tableName(): string
    {
        return 'categories';
    }

    protected function fillableAttributes(): array
    {
        return ['category_name', 'category_description', 'for_type'];
    }

    public function medias(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'category_media')->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
