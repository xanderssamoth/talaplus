<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        return [
            'comment_content',
            'answered_for',
            'type',
            'for_entity',
            'media_id',
            'product_id',
            'user_id',
            'allow_comment',
            'allow_share',
            'visibility',
            'publish_at',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [
            'allow_comment' => 'boolean',
            'allow_share' => 'boolean',
            'publish_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, ?int $userId = null): Builder
    {
        return $query->where(function (Builder $query) use ($userId): void {
            if ($userId !== null) {
                $query->where('user_id', $userId)
                    ->orWhere(function (Builder $query) use ($userId): void {
                        $this->applyPublishedVisibility($query, $userId);
                    });

                return;
            }

            $this->applyPublishedVisibility($query);
        });
    }

    private function applyPublishedVisibility(Builder $query, ?int $userId = null): void
    {
        $query
            ->where(function (Builder $query): void {
                $query->whereNull('publish_at')
                    ->orWhere(function (Builder $query): void {
                        $query->where('publish_at', '<=', now())
                            ->whereColumn('publish_at', '>=', 'created_at');
                    });
            })
            ->where(function (Builder $query) use ($userId): void {
                $query->where('visibility', 'public');

                if ($userId !== null) {
                    $query->orWhere(function (Builder $query) use ($userId): void {
                        $query->where('visibility', 'followers')
                            ->whereExists(function ($query) use ($userId): void {
                                $query->selectRaw('1')
                                    ->from('subscriptions')
                                    ->whereColumn('subscriptions.user_id', 'comments.user_id')
                                    ->where('subscriptions.follower_id', $userId);
                            });
                    });
                }
            });
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

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'hashtag_comment')->withTimestamps();
    }
}
