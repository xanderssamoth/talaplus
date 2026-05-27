<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class AboutDash extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['dash_content'];

    protected function tableName(): string
    {
        return 'about_dashes';
    }

    public function aboutContent(): BelongsTo
    {
        return $this->belongsTo(AboutContent::class);
    }
}
