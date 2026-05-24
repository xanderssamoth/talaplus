<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminNotification extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'notifications';
    }

    protected function fillableAttributes(): array
    {
        return ['type', 'is_read', 'from_user_id', 'to_user_id', 'media_id', 'product_id'];
    }

    protected function castsAttributes(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
