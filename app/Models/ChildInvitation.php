<?php

namespace App\Models;

use Database\Factories\ChildInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildInvitation extends Model
{
    /** @use HasFactory<ChildInvitationFactory> */
    use HasFactory;

    protected $fillable = ['parent_user_id', 'child_user_id', 'status', 'expires_at', 'responded_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}
