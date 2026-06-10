<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class CommentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['likes_count'] = $this->resource->reactions()->where('type', 'like')->count();
        $data['answered_for_comment'] = $this->resource->answered_for !== null
            ? self::make($this->resource->relationLoaded('answeredFor') ? $this->resource->answeredFor : $this->resource->answeredFor()->first())
            : null;

        return $data;
    }
}
