<?php

namespace App\Http\Resources\Api;

use App\Models\Category;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['product_categories'] = CategoryResource::collection($this->productCategories())->resolve($request);
        $data['product_stars_sum'] = $this->productStarsSum();
        $data['product_comments_count'] = $this->productCommentsCount();
        $data['product_customers_count'] = $this->productCustomersCount();

        return $data;
    }

    private function productCategories(): Collection
    {
        if (! $this->hasProductCategoryTables()) {
            return collect();
        }

        return Category::query()
            ->whereIn('id', function (QueryBuilder $query): void {
                $query->select('category_id')
                    ->from('products')
                    ->where('user_id', $this->resource->id)
                    ->whereNotNull('category_id');
            })
            ->latest('id')
            ->get();
    }

    private function productStarsSum(): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('reactions')) {
            return 0;
        }

        if (! Schema::hasColumn('products', 'user_id') || ! Schema::hasColumn('reactions', 'product_id') || ! Schema::hasColumn('reactions', 'number_of_stars')) {
            return 0;
        }

        $query = DB::table('reactions')
            ->join('products', 'products.id', '=', 'reactions.product_id')
            ->where('products.user_id', $this->resource->id)
            ->where('reactions.type', 'star');

        if (Schema::hasColumn('reactions', 'deleted_at')) {
            $query->whereNull('reactions.deleted_at');
        }

        if (Schema::hasColumn('products', 'deleted_at')) {
            $query->whereNull('products.deleted_at');
        }

        return (int) $query->sum('reactions.number_of_stars');
    }

    private function productCustomersCount(): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('customer_orders') || ! Schema::hasTable('carts')) {
            return 0;
        }

        foreach ([['products', 'user_id'], ['customer_orders', 'product_id'], ['customer_orders', 'cart_id'], ['carts', 'user_id']] as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                return 0;
            }
        }

        $query = DB::table('customer_orders')
            ->join('products', 'products.id', '=', 'customer_orders.product_id')
            ->join('carts', 'carts.id', '=', 'customer_orders.cart_id')
            ->where('products.user_id', $this->resource->id)
            ->whereNotNull('carts.user_id');

        if (Schema::hasColumn('customer_orders', 'deleted_at')) {
            $query->whereNull('customer_orders.deleted_at');
        }

        if (Schema::hasColumn('products', 'deleted_at')) {
            $query->whereNull('products.deleted_at');
        }

        if (Schema::hasColumn('carts', 'deleted_at')) {
            $query->whereNull('carts.deleted_at');
        }

        return $query->distinct()->count('carts.user_id');
    }

    private function productCommentsCount(): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('comments')) {
            return 0;
        }

        if (! Schema::hasColumn('products', 'user_id') || ! Schema::hasColumn('comments', 'product_id')) {
            return 0;
        }

        $query = DB::table('comments')
            ->join('products', 'products.id', '=', 'comments.product_id')
            ->where('products.user_id', $this->resource->id);

        if (Schema::hasColumn('comments', 'deleted_at')) {
            $query->whereNull('comments.deleted_at');
        }

        if (Schema::hasColumn('products', 'deleted_at')) {
            $query->whereNull('products.deleted_at');
        }

        return $query->count();
    }

    private function hasProductCategoryTables(): bool
    {
        return Schema::hasTable('products')
            && Schema::hasTable('categories')
            && Schema::hasColumn('products', 'user_id')
            && Schema::hasColumn('products', 'category_id');
    }
}
