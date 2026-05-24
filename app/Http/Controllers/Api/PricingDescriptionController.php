<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PricingDescriptionResource;
use App\Models\PricingDescription;

final class PricingDescriptionController extends ApiResourceController
{
    protected string $modelClass = PricingDescription::class;

    protected string $resourceClass = PricingDescriptionResource::class;
}
