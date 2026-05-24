<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\MediaResource;
use App\Models\AdminNotification;
use App\Models\File;
use App\Models\Hashtag;
use App\Models\History;
use App\Models\Media;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class MediaController extends ApiResourceController
{
    protected string $modelClass = Media::class;

    protected string $resourceClass = MediaResource::class;

    public function store(Request $request): JsonResponse
    {
        $media = Media::create($this->payload($request));

        $categoryIds = collect($request->input('category_ids', []))->filter()->values();
        if ($categoryIds->isNotEmpty()) {
            $media->categories()->sync($categoryIds);
        }

        $keywords = getHashtags((string) $request->input('media_description'));
        $hashtagIds = collect($keywords)->map(function (string $keyword): int {
            return Hashtag::query()->firstOrCreate(['keyword' => $keyword])->id;
        });
        $media->hashtags()->sync($hashtagIds);

        collect($request->input('files', []))->each(function (array $file) use ($media): void {
            $payload = [
                ...$file,
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

        return $this->handleResponse(MediaResource::make($media->refresh()), __('api.created'));
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

        return $this->handleResponse(MediaResource::make($media), __('api.retrieved'));
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

        return $this->handleResponse(MediaResource::make($media->refresh()), __('api.media_published'));
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

        return $this->handleResponse(MediaResource::collection($medias), __('api.retrieved'), $medias->lastPage(), $medias->total());
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

        return $this->handleResponse(ApiResource::collection($views), __('api.retrieved'), $views->lastPage(), $views->total());
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
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        return $this->storeReaction($id, $validated['user_id'], 'like');
    }

    public function gift(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pricing_id' => ['required', 'integer', 'exists:pricings,id'],
        ]);

        return $this->storeReaction($id, $validated['user_id'], 'gift', $validated['pricing_id']);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
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
        ]);

        AdminNotification::create([
            'type' => 'report_sent',
            'from_user_id' => $validated['user_id'],
            'to_user_id' => $media->user_id,
            'media_id' => $media->id,
        ]);

        return $this->handleResponse(ApiResource::make($report), __('api.created'));
    }

    private function mediaReactions(int $mediaId, string $type): JsonResponse
    {
        $reactions = Reaction::query()
            ->where('media_id', $mediaId)
            ->where('type', $type)
            ->with(['user', 'pricing'])
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($reactions), __('api.retrieved'), $reactions->lastPage(), $reactions->total());
    }

    private function storeReaction(int $mediaId, int $userId, string $type, ?int $pricingId = null): JsonResponse
    {
        $media = Media::query()->findOrFail($mediaId);
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

        return $this->handleResponse(ApiResource::make($reaction->load(['user', 'pricing'])), __('api.created'));
    }
}
