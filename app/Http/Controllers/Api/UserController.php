<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\UserResource;
use App\Models\AdminNotification;
use App\Models\History;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UserController extends ApiResourceController
{
    protected string $modelClass = User::class;

    protected string $resourceClass = UserResource::class;

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
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:45', 'unique:users,phone'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'avatar_url' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'string'],
            'christian_preference' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['created', 'activated', 'disabled', 'blocked', 'deleted'])],
            'type' => ['nullable', Rule::in(['uncertified', 'certified'])],
        ]);

        unset($validated['password_confirmation']);

        $user = User::create($validated);
        $user->api_token = $this->issuePlainTextToken($user);
        $user->save();

        $passwordReset = PasswordReset::create([
            'email' => $user->email,
            'phone' => $user->phone,
            'token' => (string) random_int(100000, 999999),
        ]);

        AdminNotification::create([
            'type' => 'welcome_new_user',
            'to_user_id' => $user->id,
        ]);

        return $this->handleResponse([
            'user' => UserResource::make($user->refresh()),
            'password_reset' => ApiResource::make($passwordReset),
        ], __('api.created'));
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
            return $this->handleError(null, __('api.invalid_credentials'), 401);
        }

        if ($user->email === $validated['username'] && $user->email_verified_at === null) {
            return $this->handleError(UserResource::make($user), __('api.email_not_verified'), 403);
        }

        if ($user->phone === $validated['username'] && $user->phone_verified_at === null) {
            return $this->handleError(UserResource::make($user), __('api.phone_not_verified'), 403);
        }

        if ($user->status === 'blocked') {
            return $this->handleError(UserResource::make($user), __('api.user_blocked'), 403);
        }

        $user->api_token = $this->issuePlainTextToken($user);
        $user->save();

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.login_success'));
    }

    public function findByUsername(string $username): JsonResponse
    {
        return $this->handleResponse(
            UserResource::make(User::query()->where('username', $username)->firstOrFail()),
            __('api.retrieved')
        );
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
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(UserResource::collection($users), __('api.retrieved'), $users->lastPage(), $users->total());
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        return $this->updateSingleAttribute($request, $id, 'status', ['created', 'activated', 'disabled', 'blocked', 'deleted']);
    }

    public function updateType(Request $request, int $id): JsonResponse
    {
        return $this->updateSingleAttribute($request, $id, 'type', ['uncertified', 'certified']);
    }

    public function updateAvatar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['avatar_url' => ['required', 'string']]);
        $user = User::query()->findOrFail($id);
        $user->update($validated);

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.updated'));
    }

    public function updatePassword(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'former_password' => ['required', 'string'],
            'new_password' => ['required', 'string'],
            'confirm_password' => ['required', 'same:new_password'],
        ]);

        $user = User::query()->findOrFail($id);

        if (! Hash::check($validated['former_password'], $user->password)) {
            return $this->handleError(UserResource::make($user), __('api.former_password_invalid'), 422);
        }

        $user->password = $validated['new_password'];
        $user->save();

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.password_updated'));
    }

    private function updateSingleAttribute(Request $request, int $id, string $attribute, array $acceptedValues): JsonResponse
    {
        $validated = $request->validate([$attribute => ['required', Rule::in($acceptedValues)]]);
        $user = User::query()->findOrFail($id);
        $user->update($validated);

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.updated'));
    }

    private function issuePlainTextToken(User $user): string
    {
        if (method_exists($user, 'createToken')) {
            return $user->createToken('auth_token')->plainTextToken;
        }

        return hash('sha256', $user->id.'|'.Str::random(80));
    }
}
