<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\UserResource;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordResetController extends BaseController
{
    public function findUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required_without_all:email,phone', 'nullable', 'string'],
            'email' => ['required_without_all:username,phone', 'nullable', 'email'],
            'phone' => ['required_without_all:username,email', 'nullable', 'string'],
        ]);

        $identifier = $validated['username'] ?? $validated['email'] ?? $validated['phone'];
        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $user) {
            return $this->handleError(null, __('api.user_not_found'), 404);
        }

        $passwordReset = PasswordReset::create([
            'email' => $user->email,
            'phone' => $user->phone,
            'token' => (string) random_int(100000, 999999),
        ]);

        return $this->handleResponse([
            'user' => UserResource::make($user),
            'password_reset' => ApiResource::make($passwordReset),
        ], __('api.retrieved'));
    }

    public function checkToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required_without_all:email,phone', 'nullable', 'string'],
            'email' => ['required_without_all:username,phone', 'nullable', 'email'],
            'phone' => ['required_without_all:username,email', 'nullable', 'string'],
            'token' => ['required', 'digits:6'],
        ]);

        $identifier = $validated['username'] ?? $validated['email'] ?? $validated['phone'];
        $passwordReset = PasswordReset::query()
            ->where('token', $validated['token'])
            ->where(function ($query) use ($identifier): void {
                $query->where('email', $identifier)->orWhere('phone', $identifier);
            })
            ->latest('id')
            ->first();

        if (! $passwordReset) {
            return $this->handleError(null, __('api.invalid_token'), 422);
        }

        return $this->handleResponse(ApiResource::make($passwordReset), __('api.token_valid'));
    }
}
