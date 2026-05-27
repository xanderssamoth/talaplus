<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\AdminNotification;
use App\Models\File;
use App\Models\History;
use App\Models\Product;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ProductController extends ApiResourceController
{
    protected string $modelClass = Product::class;

    protected string $resourceClass = ProductResource::class;

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'files', 'user', 'specifications'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ProductResource::collection($products), $this->apiMessage('find_all_success'), $products->lastPage(), $products->total());
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->payload($request));

        collect($request->file('files', []))->each(function ($uploadedFile) use ($product): void {
            File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_url' => Storage::disk('public')->url($uploadedFile->store('products/files', 'public')),
                'file_type' => str_starts_with((string) $uploadedFile->getMimeType(), 'image/') ? 'photo' : 'document',
                'user_id' => $product->user_id,
                'product_id' => $product->id,
            ]);
        });

        Role::query()
            ->where('role_name->fr', 'Administrateur')
            ->with('users:id')
            ->get()
            ->flatMap->users
            ->pluck('id')
            ->unique()
            ->each(fn (int $adminId): AdminNotification => AdminNotification::create([
                'type' => 'product_added',
                'from_user_id' => $product->user_id,
                'to_user_id' => $adminId,
                'product_id' => $product->id,
            ]));

        return $this->handleResponse(ProductResource::make($product->refresh()->load('specifications')), $this->apiMessage('created'));
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::query()->with(['category', 'files', 'user', 'specifications'])->findOrFail($id);

        if (request()->filled('user_id')) {
            History::create([
                'entity' => 'product',
                'entity_id' => $product->id,
                'action' => 'view',
                'user_id' => request()->integer('user_id'),
            ]);
        }

        return $this->handleResponse(ProductResource::make($product), $this->apiMessage('find_success'));
    }

    public function publishProduct(int $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);
        $product->is_shared = true;
        $product->save();

        AdminNotification::create([
            'type' => 'product_accepted',
            'to_user_id' => $product->user_id,
            'product_id' => $product->id,
        ]);

        return $this->handleResponse(ProductResource::make($product->refresh()), $this->apiMessage('published'));
    }

    public function popularProducts(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $viewedProductIds = History::query()
            ->where('entity', 'product')
            ->where('action', 'view')
            ->where('user_id', $validated['user_id'])
            ->pluck('entity_id');
        $preferredCategoryIds = Product::query()->whereIn('id', $viewedProductIds)->pluck('category_id')->filter()->unique()->values();

        $query = Product::query()->where('is_shared', true)->with(['category', 'user']);

        if ($preferredCategoryIds->isNotEmpty()) {
            $query->whereIn('category_id', $preferredCategoryIds);
        }

        $products = $query
            ->orderByDesc(History::query()
                ->selectRaw('count(*)')
                ->whereColumn('histories.entity_id', 'products.id')
                ->where('histories.entity', 'product')
                ->where('histories.action', 'view'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ProductResource::collection($products), $this->apiMessage('find_all_success'), $products->lastPage(), $products->total());
    }

    public function filterProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'word' => ['nullable', 'string'],
        ]);

        $products = Product::query()
            ->with(['category', 'files', 'user', 'specifications'])
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($validated['category_ids'] ?? null, fn ($query, array $categoryIds) => $query->whereIn('category_id', $categoryIds))
            ->when($validated['word'] ?? null, function ($query, string $word): void {
                $query->where(function ($query) use ($word): void {
                    $query->where('product_name', 'like', "%{$word}%")
                        ->orWhere('product_description', 'like', "%{$word}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ProductResource::collection($products), $this->apiMessage('find_all_success'), $products->lastPage(), $products->total());
    }

    public function productViews(int $id): JsonResponse
    {
        $views = History::query()
            ->where('entity', 'product')
            ->where('entity_id', $id)
            ->where('action', 'view')
            ->with('user')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($views), $this->apiMessage('find_all_success', 'history'), $views->lastPage(), $views->total());
    }

    public function productStars(int $id): JsonResponse
    {
        $stars = Reaction::query()
            ->where('product_id', $id)
            ->where('type', 'star')
            ->with('user')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(ApiResource::collection($stars), $this->apiMessage('find_all_success', 'reaction'), $stars->lastPage(), $stars->total());
    }

    public function rate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'number_of_stars' => ['nullable', 'integer', 'between:1,5'],
            'action' => ['nullable', 'string', 'in:add,remove'],
        ]);

        $product = Product::query()->findOrFail($id);

        if (($validated['action'] ?? 'add') === 'remove') {
            Reaction::query()
                ->where('type', 'star')
                ->where('product_id', $product->id)
                ->where('user_id', $validated['user_id'])
                ->delete();

            History::query()
                ->where('entity', 'product')
                ->where('entity_id', $product->id)
                ->whereIn('action', ['star', 'like'])
                ->where('user_id', $validated['user_id'])
                ->delete();

            return $this->handleResponse(null, $this->apiMessage('deleted', 'reaction'));
        }

        if (! isset($validated['number_of_stars'])) {
            return $this->handleError(['number_of_stars' => ['The number of stars field is required.']], __('validation.required', ['attribute' => 'number of stars']), 422);
        }

        $reaction = Reaction::create([
            'type' => 'star',
            'number_of_stars' => $validated['number_of_stars'],
            'product_id' => $product->id,
            'user_id' => $validated['user_id'],
        ]);

        History::create([
            'entity' => 'product',
            'entity_id' => $product->id,
            'action' => 'star',
            'user_id' => $validated['user_id'],
        ]);

        return $this->handleResponse(ApiResource::make($reaction), $this->apiMessage('created', 'reaction'));
    }

    public function report(Request $request, int $id, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'reason_id' => ['nullable', 'integer', 'exists:reasons,id'],
            'report_content' => ['nullable', 'string'],
            'muted' => ['nullable', 'boolean'],
        ]);

        $product = Product::query()->findOrFail($id);
        $report = Report::create([
            ...$validated,
            'entity' => 'product',
            'entity_id' => $product->id,
            'for_user_id' => $product->user_id,
            'user_id' => $userId,
        ]);

        AdminNotification::create([
            'type' => 'report_sent',
            'from_user_id' => $userId,
            'to_user_id' => $product->user_id,
            'product_id' => $product->id,
        ]);

        return $this->handleResponse(ApiResource::make($report), $this->apiMessage('created', 'report'));
    }
}
