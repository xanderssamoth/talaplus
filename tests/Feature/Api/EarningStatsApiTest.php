<?php

namespace Tests\Feature\Api;

use App\Models\CustomerOrder;
use App\Models\GiftTransaction;
use App\Models\History;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EarningStatsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();

        foreach (['payments', 'gift_transactions', 'subscriptions', 'histories', 'customer_orders', 'products', 'medias', 'carts', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->foreignId('belongs_to')->nullable();
            $table->string('promo_code')->nullable();
            $table->string('referral_code_used')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->integer('type')->default(1);
            $table->integer('status')->nullable();
            $table->string('reason')->nullable();
            $table->string('entity')->nullable();
            $table->foreignId('entity_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id');
            $table->foreignId('product_id');
            $table->decimal('price_at_that_time', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('histories', function (Blueprint $table): void {
            $table->id();
            $table->string('entity')->nullable();
            $table->foreignId('entity_id')->nullable();
            $table->string('action')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gift_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')->nullable();
            $table->foreignId('receiver_id')->nullable();
            $table->foreignId('history_id')->nullable();
            $table->unsignedBigInteger('coins_amount');
            $table->decimal('gross_amount', 12, 4)->nullable();
            $table->decimal('platform_fee_amount', 12, 4)->nullable();
            $table->decimal('creator_earning_amount', 12, 4)->nullable();
            $table->string('currency')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('follower_id');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
        Cache::put('exchange_rates_CDF', ['USD' => 0.0005], now()->addHour());
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_weekly_gift_marketplace_and_referral_earnings(): void
    {
        $owner = User::create(['username' => 'owner', 'promo_code' => 'ABCDE12345']);
        $buyer = User::create(['username' => 'buyer']);
        $otherSeller = User::create(['username' => 'other-seller']);
        $referred = User::create(['username' => 'referred', 'belongs_to' => $owner->id, 'referral_code_used' => 'ABCDE12345']);
        User::create(['username' => 'referred-without-follow', 'belongs_to' => $owner->id, 'referral_code_used' => 'ABCDE12345']);
        Subscription::create(['user_id' => $owner->id, 'follower_id' => $referred->id]);

        $media = Media::create(['user_id' => $owner->id]);
        $history = History::create(['user_id' => $buyer->id, 'entity' => 'media', 'entity_id' => $media->id, 'action' => 'gift']);
        GiftTransaction::query()->forceCreate([
            'sender_id' => $buyer->id, 'receiver_id' => $owner->id, 'history_id' => $history->id,
            'coins_amount' => 100, 'gross_amount' => 1, 'platform_fee_amount' => 0.3,
            'creator_earning_amount' => 0.7, 'currency' => 'USD', 'created_at' => '2026-10-20 10:00:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'gift', 'entity' => 'media', 'entity_id' => $media->id,
            'status' => 0, 'amount' => 3, 'currency' => 'USD', 'created_at' => '2026-10-21 10:00:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'gift', 'entity' => 'media', 'entity_id' => $media->id,
            'status' => 1, 'amount' => 100, 'currency' => 'USD', 'created_at' => '2026-10-21 11:00:00',
        ]);

        $product = Product::create(['user_id' => $owner->id, 'price' => 999, 'currency' => 'USD']);
        $otherProduct = Product::create(['user_id' => $otherSeller->id, 'price' => 1, 'currency' => 'USD']);
        $cartId = DB::table('carts')->insertGetId(['user_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
        CustomerOrder::query()->forceCreate(['cart_id' => $cartId, 'product_id' => $product->id, 'price_at_that_time' => 30, 'currency' => 'USD', 'quantity' => 1, 'created_at' => '2026-10-20 10:00:00']);
        $otherOrder = CustomerOrder::query()->forceCreate(['cart_id' => $cartId, 'product_id' => $otherProduct->id, 'price_at_that_time' => 70, 'currency' => 'USD', 'quantity' => 1, 'created_at' => '2026-10-20 10:00:00']);
        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'product_sale', 'entity' => 'cart', 'entity_id' => $cartId,
            'status' => 0, 'amount' => 100, 'currency' => 'USD', 'created_at' => '2026-10-22 10:00:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'product_sale', 'entity' => 'cart', 'entity_id' => $cartId,
            'status' => 2, 'amount' => 100, 'currency' => 'USD', 'created_at' => '2026-10-22 11:00:00',
        ]);
        $otherOrder->forceFill(['deleted_at' => '2026-10-23 10:00:00'])->save();
        CustomerOrder::query()->forceCreate([
            'cart_id' => $cartId, 'product_id' => $otherProduct->id,
            'price_at_that_time' => 1000, 'currency' => 'USD', 'quantity' => 1,
            'created_at' => '2026-10-23 10:00:00',
        ]);

        Payment::query()->forceCreate([
            'user_id' => $referred->id, 'reason' => 'coin_price', 'entity' => 'pricing', 'entity_id' => 1,
            'status' => 0, 'amount' => 20000, 'currency' => 'CDF', 'created_at' => '2026-10-23 10:00:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $referred->id, 'reason' => 'coin_price', 'entity' => 'pricing', 'entity_id' => 1,
            'status' => 1, 'amount' => 100, 'currency' => 'USD', 'created_at' => '2026-10-23 10:30:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $referred->id, 'reason' => 'earnings_send', 'entity' => 'user', 'entity_id' => $referred->id,
            'status' => 0, 'amount' => 100, 'currency' => 'USD', 'created_at' => '2026-10-23 11:00:00',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats?lang=fr")
            ->assertOk()
            ->assertJsonPath('data.gift_on_media.total_usd', 0.7)
            ->assertJsonPath('data.marketplace.total_usd', 30)
            ->assertJsonPath('data.referral.total_usd', 1)
            ->assertJsonPath('data.total_usd', 31.7)
            ->assertJsonPath('data.marketplace.allocation_method', 'proportional_order_snapshot')
            ->assertJsonPath('data.marketplace.estimated', true)
            ->assertJsonPath('data.gift_on_media.weeks.0.days.1.label', 'Mar')
            ->assertJsonPath('data.gift_on_media.weeks.0.days.1.amount_usd', 0.7)
            ->assertJsonPath('data.marketplace.weeks.0.days.3.amount_usd', 30)
            ->assertJsonPath('data.referral.referred_followers.0.id', $referred->id)
            ->assertJsonCount(1, 'data.referral.referred_followers')
            ->assertJsonPath('data.currency', 'USD');

        $this->getJson("/api/v1/user/{$owner->id}/earning-stats?lang=en")
            ->assertOk()
            ->assertJsonPath('data.gift_on_media.weeks.0.days.1.label', 'Tue');
    }

    public function test_last_successful_payout_resets_all_earning_branches(): void
    {
        $owner = User::create(['username' => 'owner', 'promo_code' => 'ABCDE12345']);
        $buyer = User::create(['username' => 'buyer', 'belongs_to' => $owner->id, 'referral_code_used' => 'ABCDE12345']);
        $media = Media::create(['user_id' => $owner->id]);

        foreach ([['2026-10-20 10:00:00', 100], ['2026-10-22 12:00:00', 5], ['2026-10-24 10:00:00', 20]] as [$date, $amount]) {
            $history = History::query()->forceCreate(['user_id' => $buyer->id, 'entity' => 'media', 'entity_id' => $media->id, 'action' => 'gift', 'created_at' => $date]);
            GiftTransaction::query()->forceCreate([
                'sender_id' => $buyer->id, 'receiver_id' => $owner->id, 'history_id' => $history->id,
                'coins_amount' => 100, 'creator_earning_amount' => $amount, 'currency' => 'USD', 'created_at' => $date,
            ]);
        }

        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'earnings_send', 'entity' => 'user', 'entity_id' => $owner->id,
            'status' => 0, 'amount' => 100, 'currency' => 'USD', 'created_at' => '2026-10-22 12:00:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'coin_price', 'entity' => 'pricing', 'entity_id' => 1,
            'status' => 0, 'amount' => 20, 'currency' => 'USD', 'created_at' => '2026-10-24 10:00:00',
        ]);
        Payment::query()->forceCreate([
            'user_id' => $buyer->id, 'reason' => 'earnings_send', 'entity' => 'user', 'entity_id' => $owner->id,
            'status' => 2, 'amount' => 20, 'currency' => 'USD', 'created_at' => '2026-10-25 10:00:00',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertOk()
            ->assertJsonPath('data.gift_on_media.total_usd', 20)
            ->assertJsonPath('data.referral.total_usd', 2)
            ->assertJsonPath('data.last_payout_at', '2026-10-22T12:00:00+00:00');
    }

    public function test_only_owner_can_see_statistics_and_empty_statistics_are_valid(): void
    {
        $owner = User::create(['username' => 'owner']);
        $other = User::create(['username' => 'other']);

        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")->assertUnauthorized();

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data');

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/user/999999/earning-stats')->assertNotFound();
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertOk()
            ->assertJsonPath('data.gift_on_media.total_usd', 0)
            ->assertJsonPath('data.marketplace.total_usd', 0)
            ->assertJsonPath('data.referral.total_usd', 0)
            ->assertJsonCount(0, 'data.gift_on_media.weeks');
    }

    public function test_referral_earnings_stop_when_the_code_expires_or_changes(): void
    {
        $owner = User::create(['username' => 'owner', 'promo_code' => 'ABCDE12345']);
        $referred = User::create(['username' => 'referred', 'belongs_to' => $owner->id, 'referral_code_used' => 'ABCDE12345']);
        Payment::create(['user_id' => $referred->id, 'status' => 0, 'type' => 1, 'reason' => 'coin_price', 'amount' => 100, 'currency' => 'USD']);
        Subscription::create(['user_id' => $owner->id, 'follower_id' => $referred->id]);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertOk()
            ->assertJsonPath('data.referral.total_usd', 10)
            ->assertJsonCount(1, 'data.referral.referred_followers');

        $owner->update(['promo_code' => 'FGHIJ67890']);

        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertOk()
            ->assertJsonPath('data.referral.total_usd', 0)
            ->assertJsonCount(0, 'data.referral.referred_followers');
    }

    public function test_missing_gift_valuation_returns_error_without_partial_totals(): void
    {
        $owner = User::create(['username' => 'owner']);
        $sender = User::create(['username' => 'sender']);
        $media = Media::create(['user_id' => $owner->id]);
        $history = History::create(['user_id' => $sender->id, 'entity' => 'media', 'entity_id' => $media->id, 'action' => 'gift']);
        GiftTransaction::create(['sender_id' => $sender->id, 'receiver_id' => $owner->id, 'history_id' => $history->id, 'coins_amount' => 100]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats?lang=fr")
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data');
    }

    public function test_gift_earning_uses_recorded_creator_amount_and_converts_its_currency(): void
    {
        $owner = User::create(['username' => 'owner']);
        $sender = User::create(['username' => 'sender']);
        $media = Media::create(['user_id' => $owner->id]);
        $history = History::create(['user_id' => $sender->id, 'entity' => 'media', 'entity_id' => $media->id, 'action' => 'gift']);
        GiftTransaction::create([
            'sender_id' => $sender->id, 'receiver_id' => $owner->id, 'history_id' => $history->id,
            'coins_amount' => 20, 'gross_amount' => 2000, 'platform_fee_amount' => 600,
            'creator_earning_amount' => 1400, 'currency' => 'CDF',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertOk()
            ->assertJsonPath('data.gift_on_media.total_usd', 0.7);
    }

    public function test_invalid_order_snapshot_returns_error_without_misallocating_payment(): void
    {
        $owner = User::create(['username' => 'owner']);
        $buyer = User::create(['username' => 'buyer']);
        $product = Product::create(['user_id' => $owner->id, 'price' => 20, 'currency' => 'USD']);
        $cartId = DB::table('carts')->insertGetId(['user_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
        CustomerOrder::create(['cart_id' => $cartId, 'product_id' => $product->id, 'quantity' => 1, 'price_at_that_time' => null]);
        Payment::create([
            'user_id' => $buyer->id, 'reason' => 'product_sale', 'entity' => 'cart', 'entity_id' => $cartId,
            'status' => 0, 'amount' => 20, 'currency' => 'USD',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/{$owner->id}/earning-stats")
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data');
    }
}
