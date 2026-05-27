<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedUser extends SqlModel
{
    protected function tableName(): string
    {
        return 'blocked_users';
    }

    protected function castsAttributes(): array
    {
        return [
            'is_unlocked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aboutTitle(): BelongsTo
    {
        return $this->belongsTo(AboutTitle::class);
    }
}
