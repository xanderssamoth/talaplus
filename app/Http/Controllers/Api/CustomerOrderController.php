<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CustomerOrderResource;
use App\Models\CustomerOrder;

final class CustomerOrderController extends ApiResourceController
{
    protected string $modelClass = CustomerOrder::class;

    protected string $resourceClass = CustomerOrderResource::class;
}
