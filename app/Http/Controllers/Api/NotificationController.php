<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\NotificationResource;
use App\Models\AdminNotification;

final class NotificationController extends ApiResourceController
{
    protected string $modelClass = AdminNotification::class;

    protected string $resourceClass = NotificationResource::class;
}
