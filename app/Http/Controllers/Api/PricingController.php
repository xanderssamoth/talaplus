<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PricingResource;
use App\Models\Pricing;

final class PricingController extends ApiResourceController
{
    protected string $modelClass = Pricing::class;

    protected string $resourceClass = PricingResource::class;
}
