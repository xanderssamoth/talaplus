<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Api\ApiResourceController;
use App\Http\Resources\Api\AI\AiToolCallResource;
use App\Models\AI\AiToolCall;

final class AiToolCallController extends ApiResourceController
{
    protected string $modelClass = AiToolCall::class;

    protected string $resourceClass = AiToolCallResource::class;
}
