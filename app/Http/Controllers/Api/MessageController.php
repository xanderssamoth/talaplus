<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\MessageResource;
use App\Models\Message;

final class MessageController extends ApiResourceController
{
    protected string $modelClass = Message::class;

    protected string $resourceClass = MessageResource::class;
}
