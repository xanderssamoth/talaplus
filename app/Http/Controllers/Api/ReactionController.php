<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ReactionResource;
use App\Models\Reaction;

final class ReactionController extends ApiResourceController
{
    protected string $modelClass = Reaction::class;

    protected string $resourceClass = ReactionResource::class;
}
