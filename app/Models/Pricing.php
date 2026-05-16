<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Pricing extends Model
{
    use HasTranslations;

    protected $fillable = ['pricing_name', 'pricing_type', 'reason', 'pricing_cost'];

    public array $translatable = ['pricing_name'];

    public function descriptions()
    {
        return $this->hasMany(PricingDescription::class);
    }
}
