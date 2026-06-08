<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\MediaResource;
use App\Models\AdminNotification;
use App\Models\File;
use App\Models\Hashtag;
use App\Models\History;
use App\Models\Media;
use App\Models\MediaProgress;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class MediaController extends ApiResourceController
{
    protected string $modelClass = Media::class;

    protected string $resourceClass = MediaResource::class;

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('category_ids') && $request->has('categories')) {
            $request->merge(['category_ids' => $request->input('categories')]);
        }

        Log::info('media.store.request', [
            'fields' => $request->except(['media_file', 'cover_file', 'files']),
            'files' => $this->uploadedFileSummary($request),
        ]);

        $request->validate([
            'media_url' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'string'],
            'media_file' => ['nullable', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:512000'],
            'cover_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'price' => ['nullable', 'numeric'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:512000'],
        ]);

        $payload = $this->payload($request);
        $payload['price'] ??= 0;

        if ($request->hasFile('media_file')) {
            $payload['media_url'] = Storage::disk('s3')->url($request->file('media_file')->store('medias/videos', 's3'));
        }

        if ($request->hasFile('cover_file')) {
            $payload['cover_url'] = Storage::disk('s3')->url($request->file('cover_file')->store('medias/covers', 's3'));
        }

        $media = Media::create($payload);

        $categoryIds = collect($request->input('category_ids', []))->filter()->values();
        if ($categoryIds->isNotEmpty()) {
            $media->categories()->sync($categoryIds);
        }

        $keywords = getHashtags((string) $request->input('media_description'));
        $hashtagIds = collect($keywords)->map(function (string $keyword): int {
            return Hashtag::query()->firstOrCreate(['keyword' => $keyword])->id;
        });
        $media->hashtags()->sync($hashtagIds);
        $this->syncMentions($media, (string) $request->input('media_description'));

        collect($request->file('files', []))->each(function ($uploadedFile) use ($media): void {
            $payload = [
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_url' => Storage::disk('s3')->url($uploadedFile->store('medias/files', 's3')),
                'file_type' => str_starts_with((string) $uploadedFile->getMimeType(), 'video/') ? 'video' : 'document',
                'user_id' => $media->user_id,
            ];

            if (Schema::hasColumn('files', 'media_id')) {
                $payload['media_id'] = $media->id;
            }

            File::create($payload);
        });

        $adminIds = Role::query()
            ->where('role_name->fr', 'Administrateur')
            ->with('users:id')
            ->get()
            ->flatMap->users
            ->pluck('id')
            ->unique();

        $adminIds->each(fn (int $adminId): AdminNotification => AdminNotification::create([
            'type' => 'media_created',
            'from_user_id' => $media->user_id,
            'to_user_id' => $adminId,
            'media_id' => $media->id,
        ]));

        $response = MediaResource::make($media->refresh());

        Log::info('media.store.response', [
            'success' => true,
            'data' => $response->resolve($request),
        ]);

        return $this->handleResponse($response, $this->apiMessage('created'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'media_url' => ['nullable', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:512000'],
            'cover_url' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        $media = Media::query()->findOrFail($id);
        $payload = $this->payload($request);

        if ($request->hasFile('media_url')) {
            $payload['media_url'] = Storage::disk('s3')->url($request->file('media_url')->store('medias/videos', 's3'));
        }

        if ($request->hasFile('cover_url')) {
            $payload['cover_url'] = Storage::disk('s3')->url($request->file('cover_url')->store('medias/covers', 's3'));
        }

        $media->fill($payload);
        $media->save();

        if ($request->has('category_ids')) {
            $media->categories()->sync(collect($request->input('category_ids', []))->filter()->values());
        }

        if ($request->has('media_description')) {
            $this->syncHashtags($media, (string) $request->input('media_description'));
            $this->syncMentions($media, (string) $request->input('media_description'));
        }

        return $this->handleResponse(MediaResource::make($media->refresh()), $this->apiMessage('updated'));
    }

    public function show(int $id): JsonResponse
    {
        $media = Media::query()->with(['categories', 'hashtags', 'files'])->findOrFail($id);

        if (request()->filled('user_id')) {
            History::create([
                'entity' => 'media',
                'entity_id' => $media->id,
                'action' => 'view',
                'user_id' => request()->integer('user_id'),
            ]);
        }

        $payload = request()->filled('user_id')
            ? $this->mediaPayload($media, request()->integer('user_id'))
            : MediaResource::make($media);

        return $this->handleResponse($payload, $this->apiMessage('find_success'));
    }

    public function publishMedia(int $id): JsonResponse
    {
        $media = Media::query()->findOrFail($id);
        $media->is_shared = true;
        $media->save();

        Subscription::query()
            ->where('user_id', $media->user_id)
            ->pluck('follower_id')
            ->each(fn (int $followerId): AdminNotification => AdminNotification::create([
                'type' => 'media_published',
                'from_user_id' => $media->user_id,
                'to_user_id' => $followerId,
                'media_id' => $media->id,
            ]));

        return $this->handleResponse(MediaResource::make($media->refresh()), $this->apiMessage('published'));
    }

    public function popularMedias(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::query()->findOrFail($validated['user_id']);
        $viewedMediaIds = History::query()
            ->where('entity', 'media')
            ->where('action', 'view')
            ->where('user_id', $user->id)
            ->pluck('entity_id');

        $viewedMedias = Media::query()->with('categories')->whereIn('id', $viewedMediaIds)->get();
        $preferredTypes = $viewedMedias->pluck('type')->filter()->unique()->values();
        $preferredForYouth = $viewedMedias->pluck('for_youth')->filter()->unique()->values();
        $preferredCategoryIds = $viewedMedias->flatMap->categories->pluck('id')->unique()->values();

        $query = Media::query()
            ->where('is_shared', true)
            ->with(['categories', 'user']);

        if ($user->christian_preference) {
            $query->whereHas('categories', function ($query): void {
                $query->where('category_name->fr', 'Gospel')
                    ->orWhere('category_name->fr', 'Film chrétien');
            });
        }

        if ($preferredTypes->isNotEmpty()) {
            $query->orderByRaw('case when type in ('.implode(',', array_fill(0, $preferredTypes->count(), '?')).') then 0 else 1 end', $preferredTypes->all());
        }

        if ($preferredForYouth->isNotEmpty() || $preferredCategoryIds->isNotEmpty()) {
            $query->where(function ($query) use ($preferredForYouth, $preferredCategoryIds): void {
                if ($preferredForYouth->isNotEmpty()) {
                    $query->whereIn('for_youth', $preferredForYouth);
                }

                if ($preferredCategoryIds->isNotEmpty()) {
                    $query->orWhereHas('categories', fn ($query) => $query->whereIn('categories.id', $preferredCategoryIds));
                }
            });
        }

        $medias = $query
            ->orderByDesc(History::query()
                ->selectRaw('count(*)')
                ->whereColumn('histories.entity_id', 'medias.id')
                ->where('histories.entity', 'media')
                ->where('histories.action', 'view'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $items = $medias->getCollection();
        /** @var EloquentCollection<int, Media> $items */
        $medias->setCollection($items->map(fn (Media $media): array|JsonResource => $this->mediaPayload($media, $user->id)));

        return $this->handleResponse($medias->items(), $this->apiMessage('find_all_success'), $medias->lastPage(), $medias->total());
    }

    public function filterMedias(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'hashtag_ids' => ['nullable', 'array'],
            'hashtag_ids.*' => ['integer', 'exists:hashtags,id'],
            'word' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $medias = Media::query()
            ->with(['categories', 'hashtags', 'user'])
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($validated['category_ids'] ?? null, fn ($query, array $categoryIds) => $query->whereHas(
                'categories',
                fn ($query) => $query->whereIn('categories.id', $categoryIds)
            ))
            ->when($validated['hashtag_ids'] ?? null, fn ($query, array $hashtagIds) => $query->whereHas(
                'hashtags',
                fn ($query) => $query->whereIn('hashtags.id', $hashtagIds)
            ))
            ->when($validated['word'] ?? null, function ($query, string $word): void {
                $query->where(function ($query) use ($word): void {
                    $query->where('media_title', 'like', "%{$word}%")
                        ->orWhere('media_description', 'like', "%{$word}%")
                        ->orWhere('author_names', 'like', "%{$word}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        if (isset($validated['user_id'])) {
            $items = $medias->getCollection();
            /** @var EloquentCollection<int, Media> $items */
            $medias->setCollection($items->map(fn (Media $media): array|JsonResource => $this->mediaPayload($media, $validated['user_id'])));

            return $this->handleResponse($medias->items(), $this->apiMessage('find_all_success'), $medias->lastPage(), $medias->total());
        }

        return $this->handleResponse(MediaResource::collection($medias), $this->apiMessage('find_all_success'), $medias->lastPage(), $medias->total());
    }

    public function mediaViews(int $id): JsonResponse
    {
        $views = History::query()
            ->where('entity', 'media')
            ->where('entity_id', $id)
            ->where('action', 'view')
            ->with('user')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($views), $this->apiMessage('find_all_success'), $views->lastPage(), $views->total());
    }

    public function mediaPlays(int $id): JsonResponse
    {
        $plays = History::query()
            ->where('entity', 'media')
            ->where('entity_id', $id)
            ->where('action', 'play')
            ->with('user')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($plays), $this->apiMessage('find_all_success'), $plays->lastPage(), $plays->total());
    }

    public function mediaProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_id' => ['required', 'integer', 'exists:medias,id'],
            'watched_seconds' => ['required', 'integer', 'min:0'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ((float) $validated['percentage'] > 20.0) {
            History::create([
                'entity' => 'media',
                'entity_id' => $validated['media_id'],
                'action' => 'play',
                'user_id' => $validated['user_id'],
            ]);
        }

        $progress = MediaProgress::query()->updateOrCreate(
            [
                'media_id' => $validated['media_id'],
                'user_id' => $validated['user_id'],
            ],
            [
                'watched_seconds' => $validated['watched_seconds'],
                'percentage' => $validated['percentage'],
            ]
        );

        return $this->handleResponse(ApiResource::make($progress->refresh()->load(['media', 'user'])), $this->apiMessage('created', 'media_progress'));
    }

    public function mediaLikes(int $id): JsonResponse
    {
        return $this->mediaReactions($id, 'like');
    }

    public function mediaGifts(int $id): JsonResponse
    {
        return $this->mediaReactions($id, 'gift');
    }

    public function like(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'in:add,remove'],
        ]);

        return $this->handleReaction($id, $validated['user_id'], 'like', $validated['action'] ?? 'add');
    }

    public function gift(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pricing_id' => ['nullable', 'integer', 'exists:pricings,id'],
            'action' => ['nullable', 'string', 'in:add,remove'],
        ]);

        if (($validated['action'] ?? 'add') === 'add' && ! isset($validated['pricing_id'])) {
            return $this->handleError(['pricing_id' => ['The pricing id field is required.']], __('validation.required', ['attribute' => 'pricing id']), 422);
        }

        return $this->handleReaction($id, $validated['user_id'], 'gift', $validated['action'] ?? 'add', $validated['pricing_id'] ?? null);
    }

    public function report(Request $request, int $id, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'reason_id' => ['nullable', 'integer', 'exists:reasons,id'],
            'report_content' => ['nullable', 'string'],
            'muted' => ['nullable', 'boolean'],
        ]);

        $media = Media::query()->findOrFail($id);
        $report = Report::create([
            ...$validated,
            'entity' => 'media',
            'entity_id' => $media->id,
            'for_user_id' => $media->user_id,
            'user_id' => $userId,
        ]);

        AdminNotification::create([
            'type' => 'report_sent',
            'from_user_id' => $userId,
            'to_user_id' => $media->user_id,
            'media_id' => $media->id,
        ]);

        return $this->handleResponse(ApiResource::make($report), $this->apiMessage('created', 'report'));
    }

    private function mediaReactions(int $mediaId, string $type): JsonResponse
    {
        $reactions = Reaction::query()
            ->where('media_id', $mediaId)
            ->where('type', $type)
            ->with(['user', 'pricing'])
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($reactions), $this->apiMessage('find_all_success', 'reaction'), $reactions->lastPage(), $reactions->total());
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadedFileSummary(Request $request): array
    {
        return collect(['media_file', 'cover_file', 'files'])
            ->filter(fn (string $key): bool => $request->hasFile($key))
            ->mapWithKeys(function (string $key) use ($request): array {
                $files = collect(is_array($request->file($key)) ? $request->file($key) : [$request->file($key)])
                    ->filter()
                    ->map(fn ($file): array => [
                        'name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ])
                    ->values();

                return [$key => $key === 'files' ? $files->all() : $files->first()];
            })
            ->all();
    }

    private function mediaPayload(Media $media, int $userId): array|JsonResource
    {
        $progress = $this->latestPlayedProgress($media->id, $userId);

        if ($progress === null) {
            return MediaResource::make($media);
        }

        return [
            'media' => MediaResource::make($media),
            'progress' => ApiResource::make($progress),
        ];
    }

    private function latestPlayedProgress(int $mediaId, int $userId): ?MediaProgress
    {
        $hasPlayed = History::query()
            ->where('entity', 'media')
            ->where('entity_id', $mediaId)
            ->where('action', 'play')
            ->where('user_id', $userId)
            ->exists();

        if (! $hasPlayed) {
            return null;
        }

        return MediaProgress::query()
            ->where('media_id', $mediaId)
            ->where('user_id', $userId)
            ->latest('id')
            ->first();
    }

    private function handleReaction(int $mediaId, int $userId, string $type, string $action, ?int $pricingId = null): JsonResponse
    {
        $media = Media::query()->findOrFail($mediaId);

        if ($action === 'remove') {
            Reaction::query()
                ->where('type', $type)
                ->where('media_id', $media->id)
                ->where('user_id', $userId)
                ->delete();

            History::query()
                ->where('entity', 'media')
                ->where('entity_id', $media->id)
                ->where('action', $type)
                ->where('user_id', $userId)
                ->delete();

            AdminNotification::query()
                ->where('type', $type === 'gift' ? 'gift_sent' : 'like_sent')
                ->where('from_user_id', $userId)
                ->where('to_user_id', $media->user_id)
                ->where('media_id', $media->id)
                ->delete();

            return $this->handleResponse(null, $this->apiMessage('deleted', 'reaction'));
        }

        $reaction = Reaction::create([
            'type' => $type,
            'pricing_id' => $pricingId,
            'media_id' => $media->id,
            'user_id' => $userId,
        ]);

        History::create([
            'entity' => 'media',
            'entity_id' => $media->id,
            'action' => $type,
            'user_id' => $userId,
        ]);

        AdminNotification::create([
            'type' => $type === 'gift' ? 'gift_sent' : 'like_sent',
            'from_user_id' => $userId,
            'to_user_id' => $media->user_id,
            'media_id' => $media->id,
        ]);

        return $this->handleResponse(ApiResource::make($reaction->load(['user', 'pricing'])), $this->apiMessage('created', 'reaction'));
    }

    private function syncHashtags(Media $media, string $description): void
    {
        $hashtagIds = collect(getHashtags($description))
            ->unique()
            ->map(fn (string $keyword): int => Hashtag::query()->firstOrCreate(['keyword' => $keyword])->id);

        $media->hashtags()->sync($hashtagIds);
    }

    private function syncMentions(Media $media, string $description): void
    {
        AdminNotification::query()
            ->where('type', 'mention')
            ->where('media_id', $media->id)
            ->delete();

        History::query()
            ->where('entity', 'media')
            ->where('entity_id', $media->id)
            ->where('action', 'mention')
            ->delete();

        collect(getMentions($description))
            ->unique()
            ->each(function (string $username) use ($media): void {
                $mentionedUser = User::query()->where('username', $username)->first();

                if ($mentionedUser === null || $mentionedUser->id === $media->user_id) {
                    return;
                }

                AdminNotification::create([
                    'type' => 'mention',
                    'from_user_id' => $media->user_id,
                    'to_user_id' => $mentionedUser->id,
                    'media_id' => $media->id,
                ]);

                History::create([
                    'word' => $username,
                    'entity' => 'media',
                    'entity_id' => $media->id,
                    'action' => 'mention',
                    'user_id' => $media->user_id,
                ]);
            });
    }
}
