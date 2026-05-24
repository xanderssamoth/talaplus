<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;

final class CategoryController extends ApiResourceController
{
    protected string $modelClass = Category::class;

    protected string $resourceClass = CategoryResource::class;
}
