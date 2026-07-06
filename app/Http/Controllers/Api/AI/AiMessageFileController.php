<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Api\ApiResourceController;
use App\Http\Resources\Api\AI\AiMessageFileResource;
use App\Models\AI\AiMessageFile;

final class AiMessageFileController extends ApiResourceController
{
    protected string $modelClass = AiMessageFile::class;

    protected string $resourceClass = AiMessageFileResource::class;
}
