<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\UserResource;
use App\Models\AdminNotification;
use App\Models\File;
use App\Models\History;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $roleId = $validated['role_id'] ?? null;
        unset($validated['password_confirmation']);
        unset($validated['role_id']);

        $user = User::create($validated);
        $user->api_token = $this->issuePlainTextToken($user);
        $user->save();

        if ($roleId !== null) {
            $user->roles()->attach($roleId, ['is_selected' => true]);
        }

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
        ], $this->apiMessage('created'));
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

        return $this->handleResponse(UserResource::collection($users), $this->apiMessage('find_all_success'), $users->lastPage(), $users->total());
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
        $validated = $request->validate([
            'avatar' => ['required_without:avatar_url', 'nullable', 'file', 'max:5120'],
            'avatar_url' => ['required_without:avatar', 'nullable', 'string'],
        ]);

        $user = User::query()->findOrFail($id);
        $avatarUrl = $validated['avatar_url'] ?? Storage::disk('public')->url($request->file('avatar')->store('users/avatars', 'public'));
        $user->update(['avatar_url' => $avatarUrl]);

        if ($request->hasFile('avatar')) {
            File::create([
                'file_name' => $request->file('avatar')->getClientOriginalName(),
                'file_url' => $avatarUrl,
                'file_type' => 'photo',
                'user_id' => $user->id,
            ]);
        }

        return $this->handleResponse(UserResource::make($user->refresh()), $this->apiMessage('updated'));
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
            return $this->handleError(UserResource::make($user), __('api.auth.former_password_invalid'), 422);
        }

        $user->password = $validated['new_password'];
        $user->save();

        return $this->handleResponse(UserResource::make($user->refresh()), __('api.auth.password_updated'));
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['role_id' => ['required', 'integer', 'exists:roles,id']]);
        $user = User::query()->findOrFail($id);
        $user->roles()->sync([$validated['role_id'] => ['is_selected' => true]]);

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
            $url = Storage::disk('public')->url($uploadedFile->store('users/files', 'public'));

            return File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_url' => $url,
                'file_description' => $validated['file_description'] ?? null,
                'file_type' => $validated['file_type'] ?? 'document',
                'user_id' => $user->id,
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
}
