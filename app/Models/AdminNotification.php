<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['type', 'is_read', 'from_user_id', 'to_user_id', 'media_id'];
}
