<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends SqlModel
{
    protected function tableName(): string
    {
        return 'subscriptions';
    }

    protected function fillableAttributes(): array
    {
        return ['user_id', 'follower_id', 'granted'];
    }

    protected function castsAttributes(): array
    {
        return [
            'granted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }
}
