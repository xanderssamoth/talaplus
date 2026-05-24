<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ReportResource;
use App\Models\Report;

final class ReportController extends ApiResourceController
{
    protected string $modelClass = Report::class;

    protected string $resourceClass = ReportResource::class;
}
