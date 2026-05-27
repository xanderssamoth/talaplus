<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hashtag extends SqlModel
{
    protected function tableName(): string
    {
        return 'hashtags';
    }

    public function medias(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'hashtag_media')->withTimestamps();
    }

    public function comments(): BelongsToMany
    {
        return $this->belongsToMany(Comment::class, 'hashtag_comment')->withTimestamps();
    }
}
