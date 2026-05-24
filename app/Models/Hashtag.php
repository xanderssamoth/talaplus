<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hashtag extends SqlModel
{
    protected function tableName(): string
    {
        return 'hashtags';
    }

    protected function fillableAttributes(): array
    {
        return ['keyword'];
    }

    public function medias(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'hashtag_media')->withTimestamps();
    }
}
