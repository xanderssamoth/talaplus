<?php

namespace App\Http\Resources\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

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

        if ($this->resource instanceof Model && array_key_exists('created_at', $data)) {
            $createdAt = $this->resource->getAttribute('created_at');
            $data['created_at_explicit'] = $createdAt !== null ? explicitDate($createdAt) : null;
        }

        if ($this->resource instanceof Model && method_exists($this->resource, 'files')) {
            $relation = $this->resource->files();

            if ($relation instanceof HasMany && Schema::hasColumn($relation->getRelated()->getTable(), $relation->getForeignKeyName())) {
                $files = $this->resource->relationLoaded('files') ? $this->resource->getRelation('files') : $relation->get();
                $data['files'] = $files->map(fn (Model $file): array => $file->attributesToArray())->values();
            }
        }

        return $data;
    }
}
