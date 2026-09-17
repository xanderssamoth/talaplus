<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserCartApiTest extends TestCase
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

        foreach (['customer_orders', 'carts', 'products', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('currency', 45)->nullable();
            $table->text('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name');
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
            $table->foreignId('user_id')->nullable();
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

        Schema::create('customer_orders', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price_at_that_time', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
            $table->integer('quantity')->nullable();
            $table->foreignId('product_id');
            $table->foreignId('cart_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
        $this->app->instance(ExchangeRateService::class, new UserCartFakeExchangeRateService);
    }

    public function test_my_cart_returns_products_with_historical_prices_converted_to_the_user_currency(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'currency' => 'CDF', 'password' => 'password']);
        $product = Product::create(['product_name' => 'Book', 'price' => 99, 'currency' => 'CDF']);
        $cart = Cart::create(['user_id' => $user->id]);
        CustomerOrder::create([
            'price_at_that_time' => 10,
            'currency' => 'USD',
            'quantity' => 2,
            'product_id' => $product->id,
            'cart_id' => $cart->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/user/{$user->id}/cart")
            ->assertOk()
            ->assertJsonPath('data.currency', 'CDF')
            ->assertJsonPath('data.items.0.product.id', $product->id)
            ->assertJsonPath('data.items.0.price_at_that_time', 10)
            ->assertJsonPath('data.items.0.currency_at_that_time', 'USD')
            ->assertJsonPath('data.items.0.converted_price', 10000)
            ->assertJsonPath('data.items.0.total', 20000)
            ->assertJsonPath('data.total', 20000);
    }

    public function test_my_cart_cannot_be_viewed_by_another_user(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'currency' => 'USD', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'currency' => 'USD', 'password' => 'password']);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/user/{$owner->id}/cart")
            ->assertForbidden();
    }
}

class UserCartFakeExchangeRateService extends ExchangeRateService
{
    public function convert(float $amount, string $from, string $to): float
    {
        return strtoupper($from) === strtoupper($to) ? $amount : $amount * 1000;
    }
}
