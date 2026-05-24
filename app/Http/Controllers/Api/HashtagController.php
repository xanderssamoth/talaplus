<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\HashtagResource;
use App\Models\Hashtag;

final class HashtagController extends ApiResourceController
{
    protected string $modelClass = Hashtag::class;

    protected string $resourceClass = HashtagResource::class;
}
