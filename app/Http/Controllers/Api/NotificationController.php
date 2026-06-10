<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\NotificationResource;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;

final class NotificationController extends ApiResourceController
{
    protected string $modelClass = AdminNotification::class;

    protected string $resourceClass = NotificationResource::class;

    public function userNotifications(int $userId): JsonResponse
    {
        $notifications = AdminNotification::query()
            ->where('to_user_id', $userId)
            ->with(['fromUser', 'toUser', 'media', 'product', 'comment'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(NotificationResource::collection($notifications), $this->apiMessage('find_all_success'), $notifications->lastPage(), $notifications->total());
    }

    public function markAsRead(int $id): JsonResponse
    {
        $notification = AdminNotification::query()->findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return $this->handleResponse(NotificationResource::make($notification->refresh()->load(['fromUser', 'toUser', 'media', 'product', 'comment'])), $this->apiMessage('updated'));
    }

    public function markAllAsRead(int $userId): JsonResponse
    {
        AdminNotification::query()
            ->where('to_user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $notifications = AdminNotification::query()
            ->where('to_user_id', $userId)
            ->with(['fromUser', 'toUser', 'media', 'product', 'comment'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(NotificationResource::collection($notifications), $this->apiMessage('updated'), $notifications->lastPage(), $notifications->total());
    }
}
