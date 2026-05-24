<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\AboutContentResource;
use App\Models\AboutContent;

final class AboutContentController extends ApiResourceController
{
    protected string $modelClass = AboutContent::class;

    protected string $resourceClass = AboutContentResource::class;
}
