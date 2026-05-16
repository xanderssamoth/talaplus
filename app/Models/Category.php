<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasTranslations;

    protected $fillable = ['category_name', 'category_description'];

    public array $translatable = ['category_name', 'category_description'];
}
