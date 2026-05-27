<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Reason extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['reason_content'];

    protected function tableName(): string
    {
        return 'reasons';
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
