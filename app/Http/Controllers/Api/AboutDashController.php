<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\AboutDashResource;
use App\Models\AboutDash;

final class AboutDashController extends ApiResourceController
{
    protected string $modelClass = AboutDash::class;

    protected string $resourceClass = AboutDashResource::class;
}
