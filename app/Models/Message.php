<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'message_content',
        'answered_for',
        'status',
        'user_id',
        'addressee_user_id',
        'addressee_group_id',
    ];
}
