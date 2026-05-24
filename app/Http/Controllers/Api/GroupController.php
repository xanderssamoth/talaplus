<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\GroupResource;
use App\Models\Group;

final class GroupController extends ApiResourceController
{
    protected string $modelClass = Group::class;

    protected string $resourceClass = GroupResource::class;
}
