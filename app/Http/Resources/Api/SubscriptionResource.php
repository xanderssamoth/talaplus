<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class SubscriptionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['follower'] = $this->resource->follower_id !== null
            ? UserResource::make($this->resource->relationLoaded('follower') ? $this->resource->follower : $this->resource->follower()->first())
            : null;

        return $data;
    }
}
