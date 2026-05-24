<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PromoCodeResource;
use App\Models\PromoCode;

final class PromoCodeController extends ApiResourceController
{
    protected string $modelClass = PromoCode::class;

    protected string $resourceClass = PromoCodeResource::class;
}
