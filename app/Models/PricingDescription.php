<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class PricingDescription extends Model
{
    use HasTranslations;

    protected $fillable = ['description_title', 'description_content', 'pricing_id'];

    public array $translatable = ['description_title', 'description_content'];
}
