<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartPurchaseApiTest extends TestCase
{
    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();

        foreach (['payments', 'customer_orders', 'carts', 'products', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->text('password')->nullable();
            $table->string('currency', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->text('payment_code')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_orders', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price_at_that_time', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
            $table->integer('quantity')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->foreignId('cart_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 45)->nullable();
            $table->string('provider_reference', 45)->nullable();
            $table->text('order_number')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('amount_customer', 12, 2)->nullable();
            $table->string('phone', 45)->nullable();
            $table->string('currency', 45)->nullable();
            $table->string('channel', 45)->nullable();
            $table->integer('type');
            $table->integer('status')->nullable();
            $table->string('reason')->nullable();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
            $table->foreignId('user_id')->nullable();
        });

        Schema::enableForeignKeyConstraints();

        config([
            'services.flexpay.api_token' => 'test-token',
            'services.flexpay.merchant' => 'merchant-code',
            'services.flexpay.gateway_mobile' => 'https://flexpay.test/mobile',
        ]);
    }

    public function test_cart_purchase_uses_the_current_prices_of_all_its_products(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password', 'currency' => 'USD']);
        $cart = Cart::create(['user_id' => $user->id]);
        $firstProduct = Product::create(['price' => 20.50, 'currency' => 'USD']);
        $secondProduct = Product::create(['price' => 5.25, 'currency' => 'USD']);
        CustomerOrder::create(['price_at_that_time' => 10.50, 'currency' => 'USD', 'quantity' => 2, 'product_id' => $firstProduct->id, 'cart_id' => $cart->id]);
        CustomerOrder::create(['price_at_that_time' => 4.25, 'currency' => 'USD', 'quantity' => 3, 'product_id' => $secondProduct->id, 'cart_id' => $cart->id]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '0', 'message' => 'Push sent.', 'orderNumber' => 'FLEX-CART-1'])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/purchase', [
                'cart_id' => $cart->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.order_number', 'FLEX-CART-1')
            ->assertJsonPath('data.order_number', 'FLEX-CART-1');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('56.75', $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertSame('product_sale', $payment->reason);
        $this->assertSame($cart->id, $payment->entity_id);

        Http::assertSent(fn (Request $request): bool => $request['amount'] === 56.75
            && $request['currency'] === 'USD'
            && $request['phone'] === '243810000000');
    }

    public function test_cart_purchase_rejects_an_empty_cart_without_calling_flexpay(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password', 'currency' => 'USD']);
        $cart = Cart::create(['user_id' => $user->id]);
        Http::preventStrayRequests();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/purchase', [
                'cart_id' => $cart->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_cart_purchase_rejects_a_cart_owned_by_another_user(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password', 'currency' => 'USD']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password', 'currency' => 'USD']);
        $cart = Cart::create(['user_id' => $owner->id]);
        $product = Product::create(['price' => 10, 'currency' => 'USD']);
        CustomerOrder::create(['price_at_that_time' => 10, 'currency' => 'USD', 'quantity' => 1, 'product_id' => $product->id, 'cart_id' => $cart->id]);
        Http::preventStrayRequests();

        $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/v1/cart/purchase', [
                'cart_id' => $cart->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_cart_purchase_converts_each_current_product_price_to_the_user_currency(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password', 'currency' => 'CDF']);
        $cart = Cart::create(['user_id' => $user->id]);
        $firstProduct = Product::create(['price' => 10, 'currency' => 'USD']);
        $secondProduct = Product::create(['price' => 10, 'currency' => 'CDF']);
        CustomerOrder::create(['price_at_that_time' => 100, 'currency' => 'USD', 'quantity' => 1, 'product_id' => $firstProduct->id, 'cart_id' => $cart->id]);
        CustomerOrder::create(['price_at_that_time' => 100, 'currency' => 'CDF', 'quantity' => 1, 'product_id' => $secondProduct->id, 'cart_id' => $cart->id]);
        $this->app->instance(ExchangeRateService::class, new FakeExchangeRateService);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '0', 'orderNumber' => 'FLEX-CART-2'])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/purchase', [
                'cart_id' => $cart->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.amount', '10010.00')
            ->assertJsonPath('data.payment.currency', 'CDF');

        Http::assertSent(fn (Request $request): bool => $request['amount'] === 10010.0 && $request['currency'] === 'CDF');
    }

    public function test_cart_purchase_returns_an_error_when_flexpay_rejects_the_payment(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password', 'currency' => 'USD']);
        $cart = Cart::create(['user_id' => $user->id]);
        $product = Product::create(['price' => 10, 'currency' => 'USD']);
        CustomerOrder::create(['price_at_that_time' => 10, 'currency' => 'USD', 'quantity' => 1, 'product_id' => $product->id, 'cart_id' => $cart->id]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '1', 'message' => 'Payment rejected.'])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/purchase', [
                'cart_id' => $cart->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Payment rejected.');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_cart_purchase_rejects_an_order_when_its_product_no_longer_exists(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password', 'currency' => 'USD']);
        $cart = Cart::create(['user_id' => $user->id]);
        CustomerOrder::create(['price_at_that_time' => 10, 'currency' => 'USD', 'quantity' => 1, 'product_id' => 999, 'cart_id' => $cart->id]);
        Http::preventStrayRequests();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/cart/purchase', [
                'cart_id' => $cart->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertSame(0, Payment::query()->count());
    }
}

class FakeExchangeRateService extends ExchangeRateService
{
    public function convert(float $amount, string $from, string $to): float
    {
        return strtoupper($from) === strtoupper($to) ? $amount : $amount * 1000;
    }
}
