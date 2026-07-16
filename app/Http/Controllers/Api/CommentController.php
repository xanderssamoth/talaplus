<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\CommentResource;
use App\Models\AdminNotification;
use App\Models\Comment;
use App\Models\File;
use App\Models\Hashtag;
use App\Models\History;
use App\Models\Media;
use App\Models\Product;
use App\Models\Reaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class CommentController extends ApiResourceController
{
    protected string $modelClass = Comment::class;

    protected string $resourceClass = CommentResource::class;

    public function index(Request $request): JsonResponse
    {
        $userId = $request->integer('user_id') ?: null;

        if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found', 'user'));
        }

        $comments = Comment::query()
            ->visibleTo($userId)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(
            CommentResource::collection($comments),
            $this->apiMessage('find_all_success'),
            $comments->lastPage(),
            $comments->total()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        if (($payload['media_id'] ?? null) !== null && ! Media::query()->whereKey($payload['media_id'])->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found', 'media'));
        }

        if (($payload['product_id'] ?? null) !== null && ! Product::query()->whereKey($payload['product_id'])->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found', 'product'));
        }

        if (($payload['answered_for'] ?? null) !== null && ! Comment::query()->whereKey($payload['answered_for'])->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found'));
        }

        $comment = Comment::create($payload);
        $comment->load(['media', 'product']);
        $this->syncHashtags($comment, (string) $request->input('comment_content'));
        $this->syncMentions($comment, (string) $request->input('comment_content'));
        $this->storeFiles($request, $comment);

        if ($comment->type === 'post') {
            Subscription::query()
                ->where('user_id', $comment->user_id)
                ->pluck('follower_id')
                ->each(fn (int $followerId): AdminNotification => AdminNotification::create([
                    'type' => 'post_sent',
                    'from_user_id' => $comment->user_id,
                    'to_user_id' => $followerId,
                    'comment_id' => $comment->id,
                ]));

            History::create([
                'entity' => 'comment',
                'entity_id' => $comment->id,
                'action' => 'post',
                'user_id' => $comment->user_id,
            ]);

            return $this->handleResponse(CommentResource::make($comment->refresh()->load(['files', 'user'])), $this->apiMessage('created'));
        }

        if ($comment->media_id !== null && $comment->media?->user_id !== null) {
            $parentComment = $comment->answered_for !== null ? Comment::query()->find($comment->answered_for) : null;

            AdminNotification::create([
                'type' => 'comment_sent',
                'from_user_id' => $comment->user_id,
                'to_user_id' => $parentComment?->user_id ?? $comment->media->user_id,
                'media_id' => $comment->media_id,
                'comment_id' => $parentComment?->id,
            ]);

            History::create([
                'entity' => 'media',
                'entity_id' => $comment->media_id,
                'action' => 'comment',
                'user_id' => $comment->user_id,
            ]);
        }

        if ($comment->product_id !== null && $comment->product?->user_id !== null) {
            $parentComment = $comment->answered_for !== null ? Comment::query()->find($comment->answered_for) : null;

            AdminNotification::create([
                'type' => 'comment_sent',
                'from_user_id' => $comment->user_id,
                'to_user_id' => $parentComment?->user_id ?? $comment->product->user_id,
                'product_id' => $comment->product_id,
                'comment_id' => $parentComment?->id,
            ]);

            History::create([
                'entity' => 'product',
                'entity_id' => $comment->product_id,
                'action' => 'comment',
                'user_id' => $comment->user_id,
            ]);
        }

        return $this->handleResponse(CommentResource::make($comment->refresh()->load(['files', 'user'])), $this->apiMessage('created'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $comment = Comment::query()->findOrFail($id);
        $payload = $this->payload($request);

        if (($payload['media_id'] ?? null) !== null && ! Media::query()->whereKey($payload['media_id'])->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found', 'media'));
        }

        if (($payload['product_id'] ?? null) !== null && ! Product::query()->whereKey($payload['product_id'])->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found', 'product'));
        }

        if (($payload['answered_for'] ?? null) !== null && ! Comment::query()->whereKey($payload['answered_for'])->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found'));
        }

        $comment->fill($payload);
        $comment->save();

        if ($request->has('comment_content')) {
            $this->syncHashtags($comment, (string) $request->input('comment_content'));
            $this->syncMentions($comment, (string) $request->input('comment_content'));
        }

        $this->storeFiles($request, $comment);

        return $this->handleResponse(CommentResource::make($comment->refresh()->load(['files', 'user'])), $this->apiMessage('updated'));
    }

    public function commentLikes(int $id): JsonResponse
    {
        $comment = Comment::query()->findOrFail($id);
        $likes = Reaction::query()
            ->where('comment_id', $comment->id)
            ->where('type', 'like')
            ->with('user')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($likes), $this->apiMessage('find_all_success', 'reaction'), $likes->lastPage(), $likes->total());
    }

    public function share(int $commentId): JsonResponse
    {
        $comment = Comment::query()->findOrFail($commentId);
        $post = Comment::create([
            'comment_content' => "-shared-{$comment->id}",
            'type' => 'post',
            'user_id' => $comment->user_id,
        ]);

        return $this->handleResponse(CommentResource::make($post->refresh()->load('user')), $this->apiMessage('created'));
    }

    public function newsFeed(Request $request): JsonResponse
    {
        $userId = $request->integer('user_id');

        if ($userId === 0 || ! User::query()->whereKey($userId)->exists()) {
            return $this->handleError(null, $this->apiMessage('not_found', 'user'));
        }

        $posts = Comment::query()
            ->where('type', 'post')
            ->visibleTo($userId)
            ->orderByRaw(
                <<<'SQL'
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM reactions
                        WHERE reactions.comment_id = comments.id
                            AND reactions.user_id = ?
                            AND reactions.type = 'like'
                            AND reactions.deleted_at IS NULL
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM comments AS replies
                        WHERE replies.answered_for = comments.id
                            AND replies.user_id = ?
                            AND replies.type = 'comment'
                            AND replies.deleted_at IS NULL
                    )
                    THEN 0
                    WHEN EXISTS (
                        SELECT 1
                        FROM subscriptions
                        WHERE subscriptions.user_id = comments.user_id
                            AND subscriptions.follower_id = ?
                    )
                    THEN 1
                    ELSE 2
                END
                SQL,
                [$userId, $userId, $userId]
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(CommentResource::collection($posts), $this->apiMessage('find_all_success'), $posts->lastPage(), $posts->total());
    }

    public function like(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'in:add,remove'],
        ]);
        $comment = Comment::query()->findOrFail($id);

        if (($validated['action'] ?? 'add') === 'remove') {
            Reaction::query()
                ->where('type', 'like')
                ->where('comment_id', $comment->id)
                ->where('user_id', $validated['user_id'])
                ->delete();

            History::query()
                ->where('entity', 'comment')
                ->where('entity_id', $comment->id)
                ->where('action', 'like')
                ->where('user_id', $validated['user_id'])
                ->delete();

            AdminNotification::query()
                ->where('type', 'like_sent')
                ->where('from_user_id', $validated['user_id'])
                ->where('to_user_id', $comment->user_id)
                ->where('comment_id', $comment->id)
                ->delete();

            return $this->handleResponse(null, $this->apiMessage('deleted', 'reaction'));
        }

        $reaction = Reaction::create([
            'type' => 'like',
            'comment_id' => $comment->id,
            'user_id' => $validated['user_id'],
        ]);

        History::create([
            'entity' => 'comment',
            'entity_id' => $comment->id,
            'action' => 'like',
            'user_id' => $validated['user_id'],
        ]);

        if ($comment->user_id !== null) {
            AdminNotification::create([
                'type' => 'like_sent',
                'from_user_id' => $validated['user_id'],
                'to_user_id' => $comment->user_id,
                'comment_id' => $comment->id,
            ]);
        }

        return $this->handleResponse(ApiResource::make($reaction->load('user')), $this->apiMessage('created', 'reaction'));
    }

    private function syncHashtags(Comment $comment, string $content): void
    {
        $hashtagIds = collect(getHashtags($content))
            ->unique()
            ->map(fn (string $keyword): int => Hashtag::query()->firstOrCreate(['keyword' => $keyword])->id);

        $comment->hashtags()->sync($hashtagIds);
    }

    private function syncMentions(Comment $comment, string $content): void
    {
        AdminNotification::query()
            ->where('type', 'mention')
            ->where('comment_id', $comment->id)
            ->delete();

        History::query()
            ->where('entity', 'comment')
            ->where('entity_id', $comment->id)
            ->where('action', 'mention')
            ->delete();

        collect(getMentions($content))
            ->unique()
            ->each(function (string $username) use ($comment): void {
                $mentionedUser = User::query()->where('username', $username)->first();

                if ($mentionedUser === null || $mentionedUser->id === $comment->user_id) {
                    return;
                }

                AdminNotification::create([
                    'type' => 'mention',
                    'from_user_id' => $comment->user_id,
                    'to_user_id' => $mentionedUser->id,
                    'comment_id' => $comment->id,
                ]);

                History::create([
                    'word' => $username,
                    'entity' => 'comment',
                    'entity_id' => $comment->id,
                    'action' => 'mention',
                    'user_id' => $comment->user_id,
                ]);
            });
    }

    private function storeFiles(Request $request, Comment $comment): void
    {
        $request->validate([
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
            'file_type' => ['nullable', 'string'],
            'file_description' => ['nullable', 'string'],
        ]);

        collect($request->file('files', []))->each(function ($uploadedFile) use ($request, $comment): void {
            File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_url' => Storage::disk('s3')->url($uploadedFile->store('comments/files', 's3')),
                'file_description' => $request->input('file_description'),
                'file_type' => $request->input('file_type', $this->fileTypeFromMime((string) $uploadedFile->getMimeType())),
                'user_id' => $comment->user_id,
                'comment_id' => $comment->id,
                ...File::metadataFromUploadedFile($uploadedFile),
            ]);
        });
    }

    private function fileTypeFromMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'photo',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
