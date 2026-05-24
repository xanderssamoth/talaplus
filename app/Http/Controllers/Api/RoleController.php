<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\RoleResource;
use App\Models\Role;

final class RoleController extends ApiResourceController
{
    protected string $modelClass = Role::class;

    protected string $resourceClass = RoleResource::class;
}
