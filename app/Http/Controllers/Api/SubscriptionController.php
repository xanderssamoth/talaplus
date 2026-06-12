<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\SubscriptionResource;
use App\Models\AdminNotification;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends ApiResourceController
{
    protected string $modelClass = Subscription::class;

    protected string $resourceClass = SubscriptionResource::class;

    public function store(Request $request): JsonResponse
    {
        $subscription = Subscription::create($this->payload($request));

        AdminNotification::create([
            'type' => 'new_follower',
            'from_user_id' => $subscription->follower_id,
            'to_user_id' => $subscription->user_id,
        ]);

        return $this->handleResponse(SubscriptionResource::make($subscription->refresh()), $this->apiMessage('created'));
    }

    public function isFollower(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'follower_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $subscription = Subscription::query()
            ->where('user_id', $validated['user_id'])
            ->where('follower_id', $validated['follower_id'])
            ->first();

        return $this->handleResponse([
            'is_follower' => $subscription !== null,
            'subscription' => $subscription !== null ? SubscriptionResource::make($subscription) : null,
        ], $this->apiMessage('find_success'));
    }

    public function unfollow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'follower_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        Subscription::query()
            ->where('user_id', $validated['user_id'])
            ->where('follower_id', $validated['follower_id'])
            ->delete();

        AdminNotification::query()
            ->where('type', 'new_follower')
            ->where('from_user_id', $validated['follower_id'])
            ->where('to_user_id', $validated['user_id'])
            ->delete();

        return $this->handleResponse(null, $this->apiMessage('deleted'));
    }
}
