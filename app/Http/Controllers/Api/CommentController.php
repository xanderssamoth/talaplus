<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CommentResource;
use App\Models\Comment;

final class CommentController extends ApiResourceController
{
    protected string $modelClass = Comment::class;

    protected string $resourceClass = CommentResource::class;
}
