<?php

namespace Tests\Feature;

use App\Models\GiftTransaction;
use App\Models\History;
use App\Models\Pricing;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletGiftTransactionApiTest extends TestCase
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

        foreach (['payments', 'gift_transactions', 'wallets', 'histories', 'pricings', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->text('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coins_balance')->default(0);
            $table->foreignId('user_id')->unique();
            $table->timestamps();
        });

        Schema::create('pricings', function (Blueprint $table): void {
            $table->id();
            $table->json('pricing_name');
            $table->string('pricing_type')->default('money');
            $table->unsignedBigInteger('coins_amount')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('pricing_cost', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('histories', function (Blueprint $table): void {
            $table->id();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gift_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('coins_amount');
            $table->foreignId('sender_id')->nullable();
            $table->foreignId('receiver_id')->nullable();
            $table->foreignId('pricing_id')->nullable();
            $table->foreignId('history_id')->nullable();
            $table->timestamps();
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

    public function test_my_wallet_is_created_once_for_the_authenticated_user(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => 'password']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/wallet/me')
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.coins_balance', 0);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet/me')->assertOk();

        $this->assertSame(1, Wallet::query()->where('user_id', $user->id)->count());
        $this->assertSame($user->id, $user->refresh()->wallet->user_id);
    }

    public function test_my_gift_transactions_only_returns_transactions_involving_the_authenticated_user(): void
    {
        $sender = User::create(['email' => 'sender@example.com', 'password' => 'password']);
        $receiver = User::create(['email' => 'receiver@example.com', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password']);
        $pricing = Pricing::create([
            'pricing_name' => ['fr' => 'Cadeau'],
            'reason' => 'gift_sent',
            'coins_amount' => 25,
        ]);
        $history = History::create(['entity' => 'media', 'entity_id' => 1, 'action' => 'gift', 'user_id' => $sender->id]);
        $visibleTransaction = GiftTransaction::create([
            'quantity' => 2,
            'coins_amount' => 50,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'pricing_id' => $pricing->id,
            'history_id' => $history->id,
        ]);
        GiftTransaction::create([
            'quantity' => 1,
            'coins_amount' => 25,
            'sender_id' => $otherUser->id,
            'receiver_id' => $receiver->id,
        ]);

        $this->actingAs($sender, 'sanctum')
            ->getJson('/api/v1/gift-transaction/my')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $visibleTransaction->id)
            ->assertJsonPath('data.0.sender.id', $sender->id)
            ->assertJsonPath('data.0.receiver.id', $receiver->id)
            ->assertJsonPath('data.0.pricing.coins_amount', 25)
            ->assertJsonPath('data.0.history.id', $history->id);
    }

    public function test_coin_package_purchase_initiates_a_coin_price_payment_without_crediting_the_wallet(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => 'password']);
        $pricing = Pricing::create([
            'pricing_name' => ['fr' => '100 coins'],
            'reason' => 'coin_price',
            'coins_amount' => 100,
            'pricing_cost' => 5,
            'currency' => 'USD',
        ]);
        Wallet::create(['user_id' => $user->id, 'coins_balance' => 10]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '0', 'orderNumber' => 'FLEX-COINS-1'])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/wallet/coins/purchase', [
                'pricing_id' => $pricing->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertOk()
            ->assertJsonPath('data.coins_amount', 100)
            ->assertJsonPath('data.payment.reason', 'coin_price')
            ->assertJsonPath('data.payment.entity', 'pricing')
            ->assertJsonPath('data.payment.entity_id', $pricing->id);

        $this->assertSame(10, $user->refresh()->wallet->coins_balance);
    }
}
