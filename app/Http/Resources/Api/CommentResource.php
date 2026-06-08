<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class CommentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['likes_count'] = $this->resource->reactions()->where('type', 'like')->count();
        $data['user'] = $this->resource->user_id !== null
            ? UserResource::make($this->resource->relationLoaded('user') ? $this->resource->user : $this->resource->user()->first())
            : null;

        return $data;
    }
}
