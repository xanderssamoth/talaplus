<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordReset extends SqlModel
{
    protected function tableName(): string
    {
        return 'password_resets';
    }

    /**
     * @return array<int, string>
     */
    protected function hiddenAttributes(): array
    {
        return ['former_password'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
