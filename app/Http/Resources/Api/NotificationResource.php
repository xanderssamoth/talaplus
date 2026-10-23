<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class NotificationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if (! $request->attributes->has('parental_media_lock')) {
            $request->attributes->set('parental_media_lock', $request->user()?->hasParentalMediaLock() ?? false);
        }

        $parentalMediaLock = $request->attributes->get('parental_media_lock');
        $media = $this->resource->media_id !== null
            ? ($this->resource->relationLoaded('media') ? $this->resource->media : $this->resource->media()->first())
            : null;
        $data['from_user'] = $this->resource->from_user_id !== null
            ? UserResource::make($this->resource->relationLoaded('fromUser') ? $this->resource->fromUser : $this->resource->fromUser()->first())
            : null;
        $data['to_user'] = $this->resource->to_user_id !== null
            ? UserResource::make($this->resource->relationLoaded('toUser') ? $this->resource->toUser : $this->resource->toUser()->first())
            : null;
        $data['media'] = $media !== null && (! $parentalMediaLock || $media->for_youth)
            ? MediaResource::make($media)
            : null;
        $data['product'] = $this->resource->product_id !== null
            ? ProductResource::make($this->resource->relationLoaded('product') ? $this->resource->product : $this->resource->product()->first())
            : null;
        $data['comment'] = $this->resource->comment_id !== null
            ? CommentResource::make($this->resource->relationLoaded('comment') ? $this->resource->comment : $this->resource->comment()->first())
            : null;

        return $data;
    }
}
