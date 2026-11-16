<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $parent = $this->resource->relationLoaded('parent') ? $this->resource->parent : null;

        unset($data['parent']);

        $data['parent_user'] = $parent === null ? null : [
            'id' => $parent->id,
            'firstname' => $parent->firstname,
            'lastname' => $parent->lastname,
            'surname' => $parent->surname,
            'partner_name' => $parent->partner_name,
            'username' => $parent->username,
            'avatar_url' => $parent->avatar_url,
            'cover_url' => $parent->cover_url,
        ];

        return $data;
    }
}
