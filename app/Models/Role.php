<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Role extends Model
{
    use HasTranslations;

    protected $fillable = ['role_name', 'role_description', 'created_by', 'updated_by'];

    public array $translatable = ['role_name', 'role_description'];
}
