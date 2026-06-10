<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class NotificationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['from_user'] = $this->resource->from_user_id !== null
            ? UserResource::make($this->resource->relationLoaded('fromUser') ? $this->resource->fromUser : $this->resource->fromUser()->first())
            : null;
        $data['to_user'] = $this->resource->to_user_id !== null
            ? UserResource::make($this->resource->relationLoaded('toUser') ? $this->resource->toUser : $this->resource->toUser()->first())
            : null;

        return $data;
    }
}
