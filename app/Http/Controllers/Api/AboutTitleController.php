<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\AboutTitleResource;
use App\Models\AboutTitle;

final class AboutTitleController extends ApiResourceController
{
    protected string $modelClass = AboutTitle::class;

    protected string $resourceClass = AboutTitleResource::class;
}
