<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Api\ApiResourceController;
use App\Http\Resources\Api\AI\AiMessageResource;
use App\Models\AI\AiMessage;

final class AiMessageController extends ApiResourceController
{
    protected string $modelClass = AiMessage::class;

    protected string $resourceClass = AiMessageResource::class;
}
