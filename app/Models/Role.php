<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Role extends SqlModel
{
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['role_name', 'role_description'];

    protected function tableName(): string
    {
        return 'roles';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_selected')->withTimestamps();
    }
}
