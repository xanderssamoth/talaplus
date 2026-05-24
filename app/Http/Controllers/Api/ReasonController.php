<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ReasonResource;
use App\Models\Reason;

final class ReasonController extends ApiResourceController
{
    protected string $modelClass = Reason::class;

    protected string $resourceClass = ReasonResource::class;
}
