<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Reason extends Model
{
    use HasTranslations;

    protected $fillable = ['reason_content', 'entity'];

    public array $translatable = ['reason_content'];
}
