<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\CartResource;
use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Services\ExchangeRateService;
use App\Services\FlexPayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

final class CartController extends ApiResourceController
{
    protected string $modelClass = Cart::class;

    protected string $resourceClass = CartResource::class;

    public function __construct(
        private ExchangeRateService $exchangeRateService,
        private FlexPayService $flexPayService,
    ) {}

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

    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'type' => ['required', 'integer', Rule::in([1, 2])],
            'phone' => ['required_if:type,1', 'nullable', 'string', 'max:45'],
            'description' => ['nullable', 'string'],
            'channel' => ['nullable', 'string', 'max:45'],
        ]);

        $cart = Cart::query()->findOrFail($validated['cart_id']);
        if ($request->user()?->id !== $cart->user_id) {
            return $this->handleError(null, __('api.cart.purchase_not_authorized'), 403);
        }

        $userCurrency = strtoupper((string) $request->user()->currency);

        try {
            $totals = $this->cartPaymentTotals($cart, $userCurrency);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->handleError(null, __('api.cart.prices_not_convertible'), 503);
        }

        if ($totals === null) {
            return $this->handleError(null, __('api.cart.invalid_payment_data'), 422);
        }

        try {
            $result = $this->flexPayService->initiate([
                'user_id' => $cart->user_id,
                'type' => $validated['type'],
                'amount' => $totals['amount'],
                'currency' => $totals['currency'],
                'phone' => $validated['phone'] ?? null,
                'description' => $validated['description'] ?? null,
                'channel' => $validated['channel'] ?? null,
                'reason' => 'product_sale',
                'entity' => 'cart',
                'entity_id' => $cart->id,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->handleError(null, $exception->getMessage(), 422);
        } catch (ConnectionException $exception) {
            report($exception);

            return $this->handleError(null, __('api.payment.service_unavailable'), 503);
        } catch (RequestException $exception) {
            report($exception);

            return $this->handleError(null, __('api.payment.request_failed'), 502);
        } catch (RuntimeException $exception) {
            return $this->handleError(null, $exception->getMessage(), 422);
        }

        return $this->handleResponse([
            'payment' => ApiResource::make($result['payment']),
            'message' => $result['response']['message'] ?? null,
            'order_number' => $result['payment']->order_number,
            'url' => $result['response']['url'] ?? null,
        ], __('api.entities.payment.created'));
    }

    /**
     * @return array{amount: float, currency: string}|null
     */
    private function cartPaymentTotals(Cart $cart, string $userCurrency): ?array
    {
        $orders = $cart->orders()
            ->select(['id', 'cart_id', 'product_id', 'quantity'])
            ->with('product:id,price,currency')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        if (! in_array($userCurrency, ['USD', 'CDF'], true) || $orders->contains(fn (CustomerOrder $order): bool => $order->product === null || $order->product->price === null || $order->product->price <= 0 || blank($order->product->currency) || $order->quantity === null || $order->quantity < 1)) {
            return null;
        }

        return [
            'amount' => $orders->sum(fn (CustomerOrder $order): float => $this->exchangeRateService->convert((float) $order->product->price, $order->product->currency, $userCurrency) * $order->quantity),
            'currency' => $userCurrency,
        ];
    }
}
