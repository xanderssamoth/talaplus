<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

final class CategoryController extends ApiResourceController
{
    protected string $modelClass = Category::class;

    protected string $resourceClass = CategoryResource::class;

    public function findByForType(string $forType): JsonResponse
    {
        $categories = Category::query()
            ->where('for_type', $forType)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(
            CategoryResource::collection($categories),
            $this->apiMessage('find_all_success'),
            $categories->lastPage(),
            $categories->total()
        );
    }
}
