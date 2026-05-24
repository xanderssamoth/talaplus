<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\AboutSubjectResource;
use App\Models\AboutSubject;

final class AboutSubjectController extends ApiResourceController
{
    protected string $modelClass = AboutSubject::class;

    protected string $resourceClass = AboutSubjectResource::class;
}
