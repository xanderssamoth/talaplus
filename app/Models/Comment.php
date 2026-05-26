<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'comments';
    }

    protected function fillableAttributes(): array
    {
        return ['comment_content', 'answered_for', 'media_id', 'product_id', 'user_id'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answeredFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'answered_for');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'answered_for');
    }
}
