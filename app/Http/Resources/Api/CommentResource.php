<?php

namespace App\Http\Resources\Api;

use App\Models\Comment;
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
        $data['shared_comment'] = $this->sharedComment();

        return $data;
    }

    private function sharedComment(): ?self
    {
        $content = (string) $this->resource->comment_content;

        if (preg_match('/^-shared-(\d+)/', $content, $matches) !== 1) {
            return null;
        }

        $commentId = (int) $matches[1];

        if ($commentId === (int) $this->resource->id) {
            return null;
        }

        $comment = Comment::query()->with(['files', 'user'])->find($commentId);

        return $comment !== null ? self::make($comment) : null;
    }
}
