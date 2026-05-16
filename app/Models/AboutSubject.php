<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class AboutSubject extends Model
{
    use HasTranslations;

    protected $fillable = ['subject', 'subject_description', 'status'];

    public array $translatable = ['subject', 'subject_description'];
}
