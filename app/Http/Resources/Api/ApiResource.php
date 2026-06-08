<?php

namespace App\Http\Resources\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->resource instanceof Model && array_key_exists('user_id', $data) && method_exists($this->resource, 'user')) {
            $data['user'] = $this->resource->getAttribute('user_id') !== null
                ? UserResource::make($this->resource->relationLoaded('user') ? $this->resource->getRelation('user') : $this->resource->user()->first())
                : null;
        }

        return $data;
    }
}
