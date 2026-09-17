<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

        foreach (['payments', 'reactions', 'pricings', 'medias', 'users'] as $table) {
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

    public function test_gift_initiates_a_payment_from_its_pricing_without_creating_a_pending_reaction(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $sender = User::create(['email' => 'sender@example.com', 'password' => 'password']);
        $media = Media::create(['price' => 0, 'type' => 'music', 'user_id' => $owner->id]);
        $pricing = Pricing::create(['pricing_cost' => 5.50, 'currency' => 'USD']);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '0', 'orderNumber' => 'FLEX-GIFT-1'])]);

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/v1/media/{$media->id}/gift", [
                'user_id' => $sender->id,
                'pricing_id' => $pricing->id,
                'payment_type' => 1,
                'phone' => '243810000000',
                'callback_url' => 'https://app.test/api/v1/payment/callback',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.order_number', 'FLEX-GIFT-1');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('5.50', $payment->amount);
        $this->assertSame('gift', $payment->reason);
        $this->assertSame('media', $payment->entity);
        $this->assertSame($media->id, $payment->entity_id);
        $this->assertSame(0, Reaction::query()->count());

        Http::assertSent(fn (Request $request): bool => $request['amount'] === '5.50'
            && $request['currency'] === 'USD'
            && $request['phone'] === '243810000000');
    }
}
