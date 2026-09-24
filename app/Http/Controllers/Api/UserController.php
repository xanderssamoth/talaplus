<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\MediaResource;
use App\Http\Resources\Api\UserResource;
use App\Models\AdminNotification;
use App\Models\Cart;
use App\Models\File;
use App\Models\History;
use App\Models\Media;
use App\Models\MediaProgress;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

final class UserController extends ApiResourceController
{
    protected string $modelClass = User::class;

    protected string $resourceClass = UserResource::class;

    public function __construct(
        private ExchangeRateService $exchangeRateService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('password_confirmation')) {
            $request->merge([
                'password_confirmation' => $request->input('confirm_password', $request->input('confirm_passord')),
            ]);
        }

        $validated = $request->validate([
            'firstname' => ['nullable', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'about_me' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:45', 'unique:users,phone'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['nullable', 'string', 'confirmed'],
            'password_confirmation' => ['required_with:password', 'nullable', 'string'],
            'avatar_url' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'string'],
            'christian_preference' => ['nullable', 'boolean'],
            'belongs_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['created', 'activated', 'disabled', 'blocked', 'deleted'])],
            'type' => ['nullable', Rule::in(['uncertified', 'certified'])],
        ]);

        unset($validated['password_confirmation']);

        $generatedPassword = null;
        if (($validated['password'] ?? null) === null) {
            if (($validated['email'] ?? null) !== null || ($validated['phone'] ?? null) !== null) {
                $generatedPassword = Str::password(8, true, true, false, false);
                $validated['password'] = $generatedPassword;
            } else {
                unset($validated['password']);
            }
        }

        $user = User::create($validated);
        $user->api_token = $this->issuePlainTextToken($user);
        $user->save();

        $memberRole = $this->memberRole();
        $user->roles()->attach($memberRole->id, ['is_selected' => true]);

        $passwordReset = null;
        if ($user->email !== null || $user->phone !== null) {
            $passwordReset = PasswordReset::create([
                'email' => $user->email,
                'phone' => $user->phone,
                'token' => (string) random_int(100000, 999999),
                'former_password' => $generatedPassword !== null ? Hash::make($generatedPassword) : null,
            ]);
        }

        AdminNotification::create([
            'type' => 'welcome_new_user',
            'to_user_id' => $user->id,
        ]);

        return $this->handleResponse([
            'user' => UserResource::make($user->refresh()),
            'password_reset' => $passwordReset !== null ? ApiResource::make($passwordReset) : null,
        ], $this->apiMessage('created'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->has('password_confirmation')) {
            $request->merge([
                'password_confirmation' => $request->input('confirm_password', $request->input('confirm_passord')),
            ]);
        }

        $user = User::query()->findOrFail($id);
        $payload = $this->payload($request);

        if (array_key_exists('password', $payload) && $payload['password'] !== null) {
            $request->validate([
                'password' => ['required', 'string', 'confirmed'],
                'password_confirmation' => ['required', 'string'],
            ]);
        } elseif (array_key_exists('password', $payload)) {
            unset($payload['password']);
        }

        $user->fill($payload);
        $user->save();

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['username'])
            ->orWhere('phone', $validated['username'])
            ->orWhere('username', $validated['username'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->handleError(null, __('api.auth.invalid_credentials'), 401);
        }

        if ($user->email === $validated['username'] && $user->email_verified_at === null) {
            return $this->handleError(UserResource::make($user), __('api.auth.email_not_verified'), 403);
        }

        if ($user->phone === $validated['username'] && $user->phone_verified_at === null) {
            return $this->handleError(UserResource::make($user), __('api.auth.phone_not_verified'), 403);
        }

        if ($user->status === 'blocked') {
            return $this->handleError(UserResource::make($user), __('api.auth.user_blocked'), 403);
        }

        $user->api_token = $this->issuePlainTextToken($user);
        $user->save();

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.auth.login_success'));
    }

    public function findByUsername(string $username): JsonResponse
    {
        return $this->handleResponse(
            UserResource::make(User::query()->where('username', $username)->firstOrFail()),
            $this->apiMessage('find_success')
        );
    }

    public function entrepreneurs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string'],
            'country' => ['nullable'],
            'cities' => ['nullable', 'array'],
            'cities.*' => ['string'],
            'city' => ['nullable'],
        ]);

        $categoryIds = $this->filterValues($validated['category_ids'] ?? $validated['categories'] ?? $validated['category_id'] ?? []);
        $countries = $this->filterValues($validated['countries'] ?? $validated['country'] ?? []);
        $cities = $this->filterValues($validated['cities'] ?? $validated['city'] ?? []);

        $users = User::query()
            ->whereHas('products', function ($query) use ($categoryIds): void {
                if ($categoryIds !== []) {
                    $query->whereIn('category_id', $categoryIds);
                }
            })
            ->when($countries !== [], fn ($query) => $query->whereIn('country', $countries))
            ->when($cities !== [], fn ($query) => $query->whereIn('city', $cities))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(
            UserResource::collection($users),
            $this->apiMessage('find_all_success'),
            $users->lastPage(),
            $users->total()
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function filterValues(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->filter(fn ($value): bool => filled($value))
            ->values()
            ->all();
    }

    public function hasBelongsTo(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $belongsTo = $user->belongs_to !== null ? User::query()->find($user->belongs_to) : null;

        if ($belongsTo === null) {
            return response()->json([
                'success' => false,
                'message' => $this->apiMessage('find_success'),
                'data' => null,
            ]);
        }

        return $this->handleResponse(UserResource::make($belongsTo), $this->apiMessage('find_success'));
    }

    public function switchChildLockCode(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $user->child_lock_code = filled($user->child_lock_code) ? null : $this->childLockCode();
        $user->save();

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
    }

    public function userWatchlist(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $medias = $user->watchlist()
            ->with($this->watchlistMediaRelations())
            ->latest('media_user.id')
            ->paginate(10)
            ->withQueryString();

        $items = $medias->getCollection();
        /** @var EloquentCollection<int, Media> $items */
        $medias->setCollection($items->map(fn (Media $media): array|JsonResource => $this->mediaPayload($media, $user->id)));

        return $this->handleResponse($medias->items(), $this->apiMessage('find_all_success', 'media'), $medias->lastPage(), $medias->total());
    }

    public function myCart(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        if ($request->user()?->id !== $user->id) {
            return $this->handleError(null, __('api.cart.view_not_authorized'), 403);
        }

        $currency = strtoupper((string) $user->currency);
        if ($currency === '') {
            return $this->handleError(null, __('api.cart.user_currency_required'), 422);
        }

        $cart = Cart::query()
            ->whereBelongsTo($user)
            ->latest('id')
            ->with(['orders' => fn ($query) => $query
                ->select(['id', 'cart_id', 'product_id', 'price_at_that_time', 'currency', 'quantity'])
                ->with('product')])
            ->first();

        if ($cart === null) {
            return $this->handleResponse(['currency' => $currency, 'items' => [], 'total' => 0], $this->apiMessage('find_all_success', 'cart'));
        }

        if ($cart->orders->contains(fn ($order): bool => $order->price_at_that_time === null || $order->price_at_that_time <= 0 || blank($order->currency) || $order->quantity === null || $order->quantity < 1)) {
            return $this->handleError(null, __('api.cart.invalid_order_prices'), 422);
        }

        try {
            $items = $cart->orders->map(function ($order) use ($currency): array {
                $convertedPrice = $this->exchangeRateService->convert((float) $order->price_at_that_time, $order->currency, $currency);

                return [
                    'product' => ApiResource::make($order->product),
                    'quantity' => $order->quantity,
                    'price_at_that_time' => (float) $order->price_at_that_time,
                    'currency_at_that_time' => $order->currency,
                    'converted_price' => round($convertedPrice, 2),
                    'currency' => $currency,
                    'total' => round($convertedPrice * $order->quantity, 2),
                ];
            })->values();
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->handleError(null, __('api.cart.prices_not_convertible'), 503);
        }

        return $this->handleResponse([
            'currency' => $currency,
            'items' => $items,
            'total' => round($items->sum('total'), 2),
        ], $this->apiMessage('find_all_success', 'cart'));
    }

    public function addToWatchlist(int $id, int $mediaId): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $media = Media::query()->with($this->watchlistMediaRelations())->findOrFail($mediaId);
        $user->watchlist()->syncWithoutDetaching([$media->id]);

        return $this->handleResponse($this->mediaPayload($media, $user->id), $this->apiMessage('created', 'media'));
    }

    public function removeFromWatchlist(int $id, int $mediaId): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $media = Media::query()->with($this->watchlistMediaRelations())->findOrFail($mediaId);
        $user->watchlist()->detach($media->id);

        return $this->handleResponse($this->mediaPayload($media, $user->id), $this->apiMessage('deleted', 'media'));
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => ['required', 'string'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        History::create([
            'entity' => 'user',
            'action' => 'search',
            'user_id' => $validated['user_id'],
            'word' => $validated['word'],
        ]);

        $users = User::query()
            ->where('firstname', 'like', "%{$validated['word']}%")
            ->orWhere('lastname', 'like', "%{$validated['word']}%")
            ->orWhere('surname', 'like', "%{$validated['word']}%")
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(UserResource::collection($users), $this->apiMessage('find_all_success'), $users->lastPage(), $users->total());
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['created', 'activated', 'disabled', 'blocked', 'deleted'])],
        ]);

        $user = User::query()->findOrFail($id);
        $user->update($validated);

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
    }

    public function updateType(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['uncertified', 'certified'])],
        ]);

        $user = User::query()->findOrFail($id);
        $user->update($validated);

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
    }

    public function updateAvatar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required_without:avatar_url', 'nullable', 'file', 'max:5120'],
            'avatar_url' => ['required_without:avatar', 'nullable', 'string'],
        ]);

        $user = User::query()->findOrFail($id);
        $avatarUrl = $validated['avatar_url'] ?? Storage::disk('s3')->url($request->file('avatar')->store('users/avatars', 's3'));
        $user->update(['avatar_url' => $avatarUrl]);

        if ($request->hasFile('avatar')) {
            File::create([
                'file_name' => $request->file('avatar')->getClientOriginalName(),
                'file_url' => $avatarUrl,
                'file_type' => 'photo',
                'user_id' => $user->id,
                ...File::metadataFromUploadedFile($request->file('avatar')),
            ]);
        }

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
    }

    public function updatePassword(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'former_password' => ['required', 'string'],
            'new_password' => ['required', 'string'],
            'password_confirmation' => ['required', 'same:new_password'],
        ]);

        $result = DB::transaction(function () use ($id, $validated): array {
            $user = User::query()->lockForUpdate()->findOrFail($id);

            if (! Hash::check($validated['former_password'], $user->password)) {
                return ['user' => null, 'error' => 'former_password_invalid'];
            }

            if (Hash::check($validated['new_password'], $user->password)) {
                return ['user' => null, 'error' => 'password_unchanged'];
            }

            $user->password = $validated['new_password'];
            $user->save();

            if ($user->email !== null || $user->phone !== null) {
                PasswordReset::query()->updateOrCreate(
                    ['email' => $user->email, 'phone' => $user->phone],
                    ['former_password' => Hash::make($validated['new_password'])],
                );
            }

            return ['user' => $user, 'error' => null];
        });

        /** @var ?User $user */
        $user = $result['user'];

        if ($user === null) {
            return $this->handleError(null, __('api.auth.'.$result['error']), 422);
        }

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.auth.password_updated'));
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['role_id' => ['required', 'integer', 'exists:roles,id']]);
        $user = DB::transaction(function () use ($id, $validated): User {
            $user = User::query()->lockForUpdate()->findOrFail($id);

            DB::table('role_user')
                ->where('user_id', $user->id)
                ->update([
                    'is_selected' => false,
                    'updated_at' => now(),
                ]);

            $user->roles()->syncWithoutDetaching([
                $validated['role_id'] => ['is_selected' => true],
            ]);

            return $user;
        });

        return $this->handleResponse(UserResource::make($user->refresh()->load('roles')), $this->apiMessage('updated'));
    }

    public function storeFiles(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:10240'],
            'file_type' => ['nullable', Rule::in(['video', 'photo', 'audio', 'document', 'id_card', 'ad', 'qr_code'])],
            'file_description' => ['nullable', 'string'],
        ]);

        $user = User::query()->findOrFail($id);
        $files = collect($request->file('files'))->map(function ($uploadedFile) use ($validated, $user): File {
            $url = Storage::disk('s3')->url($uploadedFile->store('users/files', 's3'));

            return File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_url' => $url,
                'file_description' => $validated['file_description'] ?? null,
                'file_type' => $validated['file_type'] ?? 'document',
                'user_id' => $user->id,
                ...File::metadataFromUploadedFile($uploadedFile),
            ]);
        });

        return $this->handleResponse(ApiResource::collection($files), __('api.file.created_many'));
    }

    private function updateSingleAttribute(Request $request, int $id, string $attribute, array $acceptedValues): JsonResponse
    {
        $validated = $request->validate([$attribute => ['required', Rule::in($acceptedValues)]]);
        $user = User::query()->findOrFail($id);
        $user->update($validated);

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
    }

    private function issuePlainTextToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    private function childLockCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($position = 0; $position < 8; $position++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }

    /**
     * @return list<string>
     */
    private function watchlistMediaRelations(): array
    {
        $relations = ['user'];

        if (Schema::hasColumn('files', 'media_id')) {
            $relations[] = 'files';
        }

        return $relations;
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

    private function memberRole(): Role
    {
        $role = Role::query()->where('role_name->fr', 'Membre')->first();

        if ($role !== null) {
            return $role;
        }

        return Role::create([
            'role_name' => [
                'fr' => 'Membre',
                'en' => 'Member',
                'ln' => 'Mosangani',
            ],
            'role_description' => [
                'fr' => 'Personne qui consulte ou commente les posts et les vidéos ; et qui commande des produits',
                'en' => 'Person who views or comments on posts and videos, and orders products',
                'ln' => 'Moto oyo atángaka to apesaka makanisi na ba posts mpe ba vidéos, mpe asombaka biloko',
            ],
        ]);
    }
}
