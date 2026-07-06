<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Api\ApiResourceController;
use App\Http\Resources\Api\AI\AiConversationResource;
use App\Models\AI\AiConversation;

final class AiConversationController extends ApiResourceController
{
    protected string $modelClass = AiConversation::class;

    protected string $resourceClass = AiConversationResource::class;
}
