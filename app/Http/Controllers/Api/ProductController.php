<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ProductResource;
use App\Models\Product;

final class ProductController extends ApiResourceController
{
    protected string $modelClass = Product::class;

    protected string $resourceClass = ProductResource::class;
}
