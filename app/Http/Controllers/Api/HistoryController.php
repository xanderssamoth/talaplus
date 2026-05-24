<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\HistoryResource;
use App\Models\History;

final class HistoryController extends ApiResourceController
{
    protected string $modelClass = History::class;

    protected string $resourceClass = HistoryResource::class;
}
