<?php

namespace Tests\Feature;

use App\Models\GiftTransaction;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Reaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaGiftPaymentTest extends TestCase
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

        foreach (['gift_transactions', 'histories', 'wallets', 'payments', 'reactions', 'pricings', 'medias', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->text('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->text('cover_url')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_audio')->default(false);
            $table->string('type')->default('music');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pricings', function (Blueprint $table): void {
            $table->id();
            $table->string('reason')->nullable();
            $table->decimal('pricing_cost', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
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
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 45)->nullable();
            $table->string('provider_reference', 45)->nullable();
            $table->text('order_number')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
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

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('coins_balance')->default(0);
            $table->foreignId('user_id')->unique();
            $table->timestamps();
        });

        Schema::create('histories', function (Blueprint $table): void {
            $table->id();
            $table->string('entity');
            $table->foreignId('entity_id');
            $table->string('action');
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gift_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('quantity');
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

        Schema::enableForeignKeyConstraints();
        config([
            'services.flexpay.api_token' => 'test-token',
            'services.flexpay.merchant' => 'merchant-code',
            'services.flexpay.gateway_mobile' => 'https://flexpay.test/mobile',
        ]);
    }

    public function test_gift_uses_wallet_coins_and_records_creator_earnings(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $sender = User::create(['email' => 'sender@example.com', 'password' => 'password']);
        $media = Media::create(['price' => 0, 'type' => 'music', 'user_id' => $owner->id]);
        $pricing = Pricing::create(['reason' => 'gift_sent', 'pricing_cost' => 20, 'currency' => 'USD']);
        Wallet::create(['user_id' => $sender->id, 'coins_balance' => 100]);
        Payment::create(['user_id' => $sender->id, 'type' => 1, 'status' => 0, 'reason' => 'coin_price', 'coin_unit_price' => 0.05, 'currency' => 'USD', 'coins_credited_at' => now()]);

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/gift", [
                'pricing_id' => $pricing->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.coins_amount', 40)
            ->assertJsonPath('data.currency', 'USD');

        $gift = GiftTransaction::query()->firstOrFail();
        $this->assertSame('2.0000', $gift->gross_amount);
        $this->assertSame('0.6000', $gift->platform_fee_amount);
        $this->assertSame('1.4000', $gift->creator_earning_amount);
        $this->assertSame(60, $sender->fresh()->wallet->coins_balance);
        $this->assertSame(1, Reaction::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }
}
