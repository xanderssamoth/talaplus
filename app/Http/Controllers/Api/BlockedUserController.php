<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\BlockedUserResource;
use App\Models\BlockedUser;

final class BlockedUserController extends ApiResourceController
{
    protected string $modelClass = BlockedUser::class;

    protected string $resourceClass = BlockedUserResource::class;
}
