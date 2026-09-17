<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class MediaResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['likes_count'] = $this->resource->reactions()->where('type', 'like')->count();
        $data['gifts_count'] = $this->resource->reactions()->where('type', 'gift')->count();
        $data['cover_url'] = filled($data['cover_url'] ?? null)
            ? $data['cover_url']
            : asset($this->resource->is_audio ? 'assets/img/cover-audio.png' : 'assets/img/cover-video.png');

        return $data;
    }
}
