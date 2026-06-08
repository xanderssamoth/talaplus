<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CommentResource;
use App\Http\Resources\Api\HashtagResource;
use App\Http\Resources\Api\MediaResource;
use App\Models\Comment;
use App\Models\Hashtag;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

final class HashtagController extends ApiResourceController
{
    protected string $modelClass = Hashtag::class;

    protected string $resourceClass = HashtagResource::class;

    public function entities(string $hashtag): JsonResponse
    {
        $keyword = ltrim($hashtag, '#');
        $record = Hashtag::query()
            ->where('id', ctype_digit($hashtag) ? (int) $hashtag : 0)
            ->orWhere('keyword', $keyword)
            ->firstOrFail();

        $mediaRelations = ['categories', 'hashtags', 'user'];

        if (Schema::hasColumn('files', 'media_id')) {
            $mediaRelations[] = 'files';
        }

        $medias = Media::query()
            ->with($mediaRelations)
            ->where(function ($query) use ($record, $keyword): void {
                $query->whereHas('hashtags', fn ($query) => $query->where('hashtags.id', $record->id))
                    ->orWhere('media_description', 'like', "%#{$keyword}%");
            })
            ->latest('id')
            ->get();

        $comments = Comment::query()
            ->with(['hashtags', 'files', 'user'])
            ->where(function ($query) use ($record, $keyword): void {
                $query->whereHas('hashtags', fn ($query) => $query->where('hashtags.id', $record->id))
                    ->orWhere('comment_content', 'like', "%#{$keyword}%");
            })
            ->latest('id')
            ->get();

        return $this->handleResponse([
            'hashtag' => HashtagResource::make($record),
            'medias' => MediaResource::collection($medias),
            'comments' => CommentResource::collection($comments),
        ], $this->apiMessage('find_success'));
    }
}
