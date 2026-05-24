<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class AboutTitle extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['title'];

    protected function tableName(): string
    {
        return 'about_titles';
    }

    protected function fillableAttributes(): array
    {
        return ['title', 'alias', 'about_subject_id'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(AboutSubject::class, 'about_subject_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(AboutContent::class);
    }

    public function blockedUsers(): HasMany
    {
        return $this->hasMany(BlockedUser::class);
    }
}
