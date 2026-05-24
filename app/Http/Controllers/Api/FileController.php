<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\FileResource;
use App\Models\File;

final class FileController extends ApiResourceController
{
    protected string $modelClass = File::class;

    protected string $resourceClass = FileResource::class;
}
