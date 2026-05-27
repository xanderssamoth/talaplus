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

        return $data;
    }
}
