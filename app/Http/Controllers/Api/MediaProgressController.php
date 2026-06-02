<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Models\MediaProgress;

final class MediaProgressController extends ApiResourceController
{
    protected string $modelClass = MediaProgress::class;

    protected string $resourceClass = ApiResource::class;
}
