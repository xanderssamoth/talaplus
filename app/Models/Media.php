<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'medias';
    protected $fillable = [
        'media_title',
        'media_description',
        'media_url',
        'author_names',
        'is_free',
        'price',
        'for_youth',
        'belongs_to',
        'type',
        'shared_at',
        'shared_by',
        'user_id',
    ];
}
