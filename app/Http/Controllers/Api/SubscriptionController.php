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
}
