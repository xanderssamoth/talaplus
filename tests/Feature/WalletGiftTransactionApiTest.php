<?php

namespace Tests\Feature;

use App\Models\GiftTransaction;
use App\Models\History;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinService;
use App\Services\GiftService;
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

        foreach (['payments', 'gift_transactions', 'reactions', 'medias', 'wallets', 'histories', 'pricings', 'users'] as $table) {
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

        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->foreignId('pricing_id')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('user_id')->nullable();
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
            $table->decimal('gross_amount', 12, 4)->nullable();
            $table->decimal('platform_fee_amount', 12, 4)->nullable();
            $table->decimal('creator_earning_amount', 12, 4)->nullable();
            $table->string('currency')->nullable();
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
            $table->unsignedBigInteger('coins_amount')->nullable();
            $table->decimal('coin_unit_price', 12, 6)->nullable();
            $table->string('phone', 45)->nullable();
            $table->string('currency', 45)->nullable();
            $table->string('channel', 45)->nullable();
            $table->integer('type');
            $table->integer('status')->nullable();
            $table->string('reason')->nullable();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
            $table->timestamp('coins_credited_at')->nullable();
            $table->foreignId('user_id')->nullable();
        });

        Schema::enableForeignKeyConstraints();

        config([
            'services.flexpay.api_token' => 'Bearer test-token',
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
            ->assertJsonPath('data.payment.reason_text', 'Achat des coins')
            ->assertJsonPath('data.payment.entity', 'pricing')
            ->assertJsonPath('data.payment.entity_id', $pricing->id);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(100, $payment->coins_amount);
        $this->assertSame('0.050000', $payment->coin_unit_price);
        $this->assertSame(10, $user->refresh()->wallet->coins_balance);
    }

    public function test_coin_credit_uses_the_purchased_quantity_even_after_pricing_changes(): void
    {
        $user = User::create(['email' => 'coin-credit@example.com']);
        $pricing = Pricing::create(['pricing_name' => ['fr' => 'Coins'], 'reason' => 'coin_price', 'coins_amount' => 200, 'pricing_cost' => 10, 'currency' => 'USD']);
        $payment = Payment::create([
            'user_id' => $user->id, 'type' => 1, 'status' => 0, 'reason' => 'coin_price',
            'entity' => 'pricing', 'entity_id' => $pricing->id, 'amount' => 10, 'currency' => 'USD',
            'coins_amount' => 100, 'coin_unit_price' => 0.1,
        ]);

        $pricing->update(['coins_amount' => 500]);
        app(CoinService::class)->completePayment($payment);
        app(CoinService::class)->completePayment($payment->fresh());

        $this->assertSame(100, $user->fresh()->wallet->coins_balance);
    }

    public function test_gift_uses_last_confirmed_coin_unit_price_and_records_thirty_percent_fee(): void
    {
        $sender = User::create(['email' => 'gift-sender@example.com']);
        $receiver = User::create(['email' => 'gift-receiver@example.com']);
        Wallet::create(['user_id' => $sender->id, 'coins_balance' => 100]);
        $media = Media::create(['user_id' => $receiver->id]);
        $giftPricing = Pricing::create(['pricing_name' => ['fr' => 'Cadeau'], 'reason' => 'gift_sent', 'pricing_cost' => 20]);
        Payment::create(['user_id' => $sender->id, 'type' => 1, 'reason' => 'coin_price', 'status' => 0, 'currency' => 'USD', 'coin_unit_price' => 0.05, 'coins_credited_at' => now()->subDay()]);
        Payment::create(['user_id' => $sender->id, 'type' => 1, 'reason' => 'coin_price', 'status' => 0, 'currency' => 'CDF', 'coin_unit_price' => 100, 'coins_credited_at' => now()]);
        Payment::create(['user_id' => $sender->id, 'type' => 1, 'reason' => 'coin_price', 'status' => 1, 'currency' => 'USD', 'coin_unit_price' => 9, 'coins_credited_at' => now()->addMinute()]);

        $gift = app(GiftService::class)->sendGift($sender, $media, $giftPricing, 2);

        $this->assertSame(40, $gift->coins_amount);
        $this->assertSame('4000.0000', $gift->gross_amount);
        $this->assertSame('1200.0000', $gift->platform_fee_amount);
        $this->assertSame('2800.0000', $gift->creator_earning_amount);
        $this->assertSame('CDF', $gift->currency);
        $this->assertSame(60, $sender->fresh()->wallet->coins_balance);
    }

    public function test_gift_without_a_confirmed_priced_coin_purchase_does_not_debit_wallet(): void
    {
        $sender = User::create(['email' => 'unpriced-sender@example.com']);
        $receiver = User::create(['email' => 'unpriced-receiver@example.com']);
        Wallet::create(['user_id' => $sender->id, 'coins_balance' => 100]);
        $media = Media::create(['user_id' => $receiver->id]);
        $giftPricing = Pricing::create(['pricing_name' => ['fr' => 'Cadeau'], 'reason' => 'gift_sent', 'pricing_cost' => 20]);

        try {
            app(GiftService::class)->sendGift($sender, $media, $giftPricing);
            $this->fail('A gift without a priced purchase should fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(__('api.gift.coin_purchase_missing'), $exception->getMessage());
        }

        $this->assertSame(100, $sender->fresh()->wallet->coins_balance);
        $this->assertSame(0, GiftTransaction::query()->count());
    }

    public function test_coin_package_purchase_returns_the_complete_provider_error_in_the_local_environment(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => 'password']);
        $pricing = Pricing::create([
            'pricing_name' => ['fr' => '100 coins'],
            'reason' => 'coin_price',
            'coins_amount' => 100,
            'pricing_cost' => 5,
            'currency' => 'USD',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://flexpay.test/mobile' => Http::response([
                'error' => 'Internal Server Error',
                'message' => 'Last encoded character is invalid.',
            ], 500),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/wallet/coins/purchase', [
                'pricing_id' => $pricing->id,
                'type' => 1,
                'phone' => '243810000000',
            ])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.payment.request_failed'))
            ->assertJsonPath('data.provider_status', 500)
            ->assertJsonPath('data.provider_response.message', 'Last encoded character is invalid.');
    }
}
