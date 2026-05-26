<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CommentResource;
use App\Models\AdminNotification;
use App\Models\Comment;
use App\Models\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommentController extends ApiResourceController
{
    protected string $modelClass = Comment::class;

    protected string $resourceClass = CommentResource::class;

    public function store(Request $request): JsonResponse
    {
        $comment = Comment::create($this->payload($request));
        $comment->load(['media', 'product']);

        if ($comment->media_id !== null && $comment->media?->user_id !== null) {
            AdminNotification::create([
                'type' => 'comment_sent',
                'from_user_id' => $comment->user_id,
                'to_user_id' => $comment->media->user_id,
                'media_id' => $comment->media_id,
            ]);

            History::create([
                'entity' => 'media',
                'entity_id' => $comment->media_id,
                'action' => 'comment',
                'user_id' => $comment->user_id,
            ]);
        }

        if ($comment->product_id !== null && $comment->product?->user_id !== null) {
            AdminNotification::create([
                'type' => 'comment_sent',
                'from_user_id' => $comment->user_id,
                'to_user_id' => $comment->product->user_id,
                'product_id' => $comment->product_id,
            ]);

            History::create([
                'entity' => 'product',
                'entity_id' => $comment->product_id,
                'action' => 'comment',
                'user_id' => $comment->user_id,
            ]);
        }

        return $this->handleResponse(CommentResource::make($comment->refresh()), $this->apiMessage('created'));
    }
}
