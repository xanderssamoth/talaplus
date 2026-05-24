<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'messages';
    }

    protected function fillableAttributes(): array
    {
        return [
            'message_content',
            'answered_for',
            'status',
            'user_id',
            'addressee_user_id',
            'addressee_group_id',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresseeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_user_id');
    }

    public function addresseeGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'addressee_group_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
