<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class AboutContent extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['subtitle', 'content'];

    protected function tableName(): string
    {
        return 'about_contents';
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(AboutTitle::class, 'about_title_id');
    }

    public function dashes(): HasMany
    {
        return $this->hasMany(AboutDash::class);
    }
}
