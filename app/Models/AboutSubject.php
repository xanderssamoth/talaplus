<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class AboutSubject extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['subject', 'subject_description'];

    protected function tableName(): string
    {
        return 'about_subjects';
    }

    protected function fillableAttributes(): array
    {
        return ['subject', 'subject_description', 'status'];
    }

    public function titles(): HasMany
    {
        return $this->hasMany(AboutTitle::class);
    }
}
