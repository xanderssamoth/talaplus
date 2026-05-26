<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\CartResource;
use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CartController extends ApiResourceController
{
    protected string $modelClass = Cart::class;

    protected string $resourceClass = CartResource::class;

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'payment_code' => ['nullable', 'string'],
        ]);

        $quantity = $validated['quantity'] ?? 1;

        return DB::transaction(function () use ($validated, $quantity): JsonResponse {
            $product = Product::query()->lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->quantity !== null && $product->quantity < $quantity) {
                return $this->handleError(null, __('api.cart.insufficient_quantity'), 422);
            }

            $cart = Cart::query()->firstOrCreate(
                ['user_id' => $validated['user_id']],
                ['payment_code' => $validated['payment_code'] ?? null]
            );

            if ($cart->orders()->where('product_id', $product->id)->exists()) {
                return $this->handleError(ApiResource::make($cart), __('api.cart.already_contains_product'), 409);
            }

            $order = CustomerOrder::create([
                'price_at_that_time' => $product->price,
                'currency' => $product->currency,
                'quantity' => $quantity,
                'product_id' => $product->id,
                'cart_id' => $cart->id,
            ]);

            if ($product->quantity !== null) {
                $product->decrement('quantity', $quantity);
            }

            return $this->handleResponse(ApiResource::make($order->load(['product', 'cart'])), __('api.cart.product_added'));
        });
    }

    public function removeFromCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        return DB::transaction(function () use ($validated): JsonResponse {
            $cart = Cart::query()->where('user_id', $validated['user_id'])->firstOrFail();
            $order = CustomerOrder::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $validated['product_id'])
                ->firstOrFail();
            $product = Product::query()->lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->quantity !== null) {
                $product->increment('quantity', (int) $order->quantity);
            }

            $order->delete();

            return $this->handleResponse(CartResource::make($cart->refresh()->load('orders')), __('api.cart.product_removed'));
        });
    }

    public function isInCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $cart = Cart::query()->where('user_id', $validated['user_id'])->first();
        $exists = $cart !== null && $cart->orders()->where('product_id', $validated['product_id'])->exists();

        return $this->handleResponse(['is_in_cart' => $exists], $this->apiMessage('find_success'));
    }
}
