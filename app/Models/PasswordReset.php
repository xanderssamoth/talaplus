<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordReset extends SqlModel
{
    protected function tableName(): string
    {
        return 'password_resets';
    }

    protected function fillableAttributes(): array
    {
        return ['email', 'phone', 'token', 'former_password'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
