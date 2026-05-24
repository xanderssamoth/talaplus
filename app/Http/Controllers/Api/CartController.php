<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CartResource;
use App\Models\Cart;

final class CartController extends ApiResourceController
{
    protected string $modelClass = Cart::class;

    protected string $resourceClass = CartResource::class;
}
