<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\File;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\User;
use App\Services\PremiumService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PremiumMembershipApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('files');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('pricings');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('type')->default('uncertified');
            $table->timestamp('premium_started_at')->nullable();
            $table->timestamp('premium_expires_at')->nullable();
            $table->unsignedTinyInteger('premium_duration_months')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_url');
            $table->string('file_type');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('pricings', function (Blueprint $table): void {
            $table->id();
            $table->json('pricing_name');
            $table->string('pricing_type')->default('money');
            $table->unsignedTinyInteger('premium_duration_months')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('pricing_cost', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->string('reference')->nullable();
            $table->string('provider_reference')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('amount_customer', 12, 2)->nullable();
            $table->string('phone')->nullable();
            $table->string('currency')->nullable();
            $table->string('channel')->nullable();
            $table->integer('type');
            $table->integer('status')->nullable();
            $table->string('reason')->nullable();
            $table->string('entity')->nullable();
            $table->foreignId('entity_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->unsignedTinyInteger('premium_duration_months')->nullable();
            $table->decimal('premium_plan_amount', 12, 2)->nullable();
            $table->string('premium_plan_currency')->nullable();
            $table->timestamp('premium_activated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->foreignId('from_user_id')->nullable();
            $table->foreignId('to_user_id')->nullable();
            $table->foreignId('payment_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::enableForeignKeyConstraints();

        foreach ([1 => 10, 3 => 20, 6 => 50, 12 => 100] as $months => $price) {
            Pricing::create([
                'pricing_name' => [
                    'fr' => $months.' mois',
                    'en' => $months.($months === 1 ? ' month' : ' months'),
                    'ln' => 'Sanza '.$months,
                ],
                'pricing_type' => 'money',
                'premium_duration_months' => $months,
                'reason' => 'user_certfied',
                'pricing_cost' => $price,
                'currency' => 'USD',
            ]);
        }

        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));
        config([
            'services.flexpay.api_token' => 'test-token',
            'services.flexpay.merchant' => 'merchant-code',
            'services.flexpay.gateway_mobile' => 'https://flexpay.test/mobile',
            'services.flexpay.gateway_check' => 'https://flexpay.test/check',
        ]);
    }

    public function test_identity_card_status_only_uses_current_users_active_files(): void
    {
        $owner = User::create(['username' => 'owner']);
        $other = User::create(['username' => 'other']);
        File::create(['file_url' => 'other-card', 'file_type' => 'id_card', 'user_id' => $other->id]);
        File::create(['file_url' => 'owner-photo', 'file_type' => 'photo', 'user_id' => $owner->id]);
        File::create(['file_url' => 'deleted-card', 'file_type' => 'id_card', 'user_id' => $owner->id])->delete();

        $this->getJson('/api/v1/user/me/id-card')->assertUnauthorized();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/user/me/id-card')->assertOk()->assertJsonPath('data.has_id_card', false);

        File::create(['file_url' => 'owner-card', 'file_type' => 'id_card', 'user_id' => $owner->id]);
        $this->getJson('/api/v1/user/me/id-card')->assertOk()->assertJsonPath('data.has_id_card', true);
    }

    public function test_premium_pricing_is_translated_at_request_time(): void
    {
        Sanctum::actingAs(User::create(['username' => 'member']));
        config(['premium.plans' => []]);

        $this->getJson('/api/v1/user/premium/pricing?lang=fr')
            ->assertOk()
            ->assertJsonPath('data.1.name', '1 mois')
            ->assertJsonPath('data.3.price', 20)
            ->assertJsonPath('data.12.currency', 'USD');

        $this->getJson('/api/v1/user/premium/pricing?lang=en')
            ->assertOk()
            ->assertJsonPath('data.1.name', '1 month')
            ->assertJsonPath('data.3.name', '3 months');

        $this->getJson('/api/v1/user/premium/pricing?lang=ln')
            ->assertOk()
            ->assertJsonPath('data.1.name', 'Sanza 1');
    }

    public function test_activation_renewal_and_expiration_keep_user_type_in_sync(): void
    {
        $user = User::create(['username' => 'member']);
        $premiumService = app(PremiumService::class);
        Http::preventStrayRequests();
        Http::fake([
            'https://flexpay.test/check/RENEW-1' => Http::response(['code' => '0', 'transaction' => ['orderNumber' => 'RENEW-1', 'status' => 0, 'amount' => 10, 'currency' => 'USD']]),
            'https://flexpay.test/check/RENEW-3' => Http::response(['code' => '0', 'transaction' => ['orderNumber' => 'RENEW-3', 'status' => 0, 'amount' => 20, 'currency' => 'USD']]),
        ]);
        $firstPayment = Payment::create([
            'order_number' => 'RENEW-1', 'amount' => 10, 'currency' => 'USD',
            'premium_plan_amount' => 10, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 1,
            'type' => 1, 'status' => 0, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $user->id, 'user_id' => $user->id,
        ]);

        $activated = $premiumService->completePayment($firstPayment);
        $this->assertSame('certified', $activated->type);
        $this->assertSame(1, $activated->premium_duration_months);
        $this->assertSame('2026-11-30', $activated->premium_expires_at->toDateString());
        $this->assertSame(30, $premiumService->daysRemaining($activated));

        $this->travelTo(now()->addDay());
        $renewalPayment = Payment::create([
            'order_number' => 'RENEW-3', 'amount' => 20, 'currency' => 'USD',
            'premium_plan_amount' => 20, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 3,
            'type' => 1, 'status' => 0, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $user->id, 'user_id' => $user->id,
        ]);
        $renewed = $premiumService->completePayment($renewalPayment);
        $this->assertSame('2027-02-28', $renewed->premium_expires_at->toDateString());
        $this->assertSame(3, $renewed->premium_duration_months);
        $this->assertSame(now()->toDateTimeString(), $renewed->premium_started_at->toDateTimeString());

        $this->travelTo($renewed->premium_expires_at);
        $this->assertFalse($premiumService->isPremium($renewed));
        $this->assertSame(0, $premiumService->daysRemaining($renewed));

        $this->artisan('premium:expire')->assertExitCode(0);
        $this->assertSame('uncertified', $user->fresh()->type);
        $this->assertNotNull($user->fresh()->premium_expires_at);
        $this->assertSame(0, $premiumService->expire());
    }

    public function test_status_and_days_remaining_use_expiration_even_before_scheduler_runs(): void
    {
        $owner = User::create(['username' => 'owner', 'type' => 'certified']);
        $owner->forceFill(['premium_expires_at' => now()->addHours(2)])->save();
        $other = User::create(['username' => 'other']);
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/user/{$owner->id}/premium")
            ->assertOk()
            ->assertJsonPath('data.is_premium', true);
        $this->getJson("/api/v1/user/{$owner->id}/premium/days-remaining")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/{$owner->id}/premium/days-remaining")
            ->assertOk()
            ->assertJsonPath('data.days_remaining', 1);

        $this->travelTo(now()->addHours(2));
        $this->getJson("/api/v1/user/{$owner->id}/premium")
            ->assertOk()
            ->assertJsonPath('data.is_premium', false);
    }

    public function test_invalid_plan_is_rejected_by_the_purchase_service(): void
    {
        $user = User::create(['username' => 'member']);
        Sanctum::actingAs($user);

        $this->expectException(InvalidArgumentException::class);
        app(PremiumService::class)->purchase($user, 2, ['type' => 1, 'phone' => '243810000000']);
    }

    public function test_one_client_call_sets_certifying_and_callback_activates_only_after_provider_verification(): void
    {
        $owner = User::create(['username' => 'owner']);
        $other = User::create(['username' => 'other']);
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/user/{$owner->id}/premium/activate", ['months' => 1, 'type' => 1, 'phone' => '243810000000'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/user/{$owner->id}/premium/activate", ['months' => 2, 'type' => 1, 'phone' => '243810000000'])->assertUnprocessable();

        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '0', 'orderNumber' => 'PREMIUM-1'])]);
        $this->postJson("/api/v1/user/{$owner->id}/premium/activate", ['months' => 1, 'type' => 1, 'phone' => '243810000000'])
            ->assertOk()
            ->assertJsonPath('data.payment.premium_duration_months', 1);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('10.00', $payment->premium_plan_amount);
        $this->assertSame('USD', $payment->premium_plan_currency);
        $this->assertSame('certifying', $owner->fresh()->type);
        $this->assertSame(1, $owner->fresh()->premium_duration_months);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($owner->fresh()->premium_started_at);
        $this->assertNull($owner->fresh()->premium_expires_at);

        Http::fake(['https://flexpay.test/check/PREMIUM-1' => Http::sequence()
            ->push(['code' => '0', 'transaction' => ['orderNumber' => 'PREMIUM-1', 'status' => Payment::STATUS_PENDING, 'amount' => 10, 'currency' => 'USD']])
            ->push(['code' => '0', 'transaction' => ['orderNumber' => 'PREMIUM-1', 'status' => 0, 'amount' => 10, 'currency' => 'USD']])
            ->push(['code' => '0', 'transaction' => ['orderNumber' => 'PREMIUM-1', 'status' => 0, 'amount' => 10, 'currency' => 'USD']])]);
        $this->postJson('/api/payment/store', ['orderNumber' => 'PREMIUM-1', 'status' => 0])->assertUnprocessable();

        $this->assertNull($payment->fresh()->premium_activated_at);
        $this->assertSame('certifying', $owner->fresh()->type);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(0, AdminNotification::query()->count());
        $this->postJson('/api/payment/store', ['orderNumber' => 'PREMIUM-1', 'status' => 0])->assertOk();
        $this->assertSame('certified', $owner->fresh()->type);
        $this->assertSame(now()->toDateTimeString(), $owner->fresh()->premium_started_at->toDateTimeString());
        $this->assertSame('payment_successful', AdminNotification::query()->firstOrFail()->type);

        $firstExpiry = $owner->fresh()->premium_expires_at;
        $this->postJson('/api/payment/store', ['orderNumber' => 'PREMIUM-1', 'status' => 2])->assertOk();
        $this->assertTrue($owner->fresh()->premium_expires_at->equalTo($firstExpiry));
        $this->assertNotNull($payment->fresh()->premium_activated_at);
        $this->assertSame(0, $payment->fresh()->status);
        $this->assertSame(1, AdminNotification::query()->count());
    }

    public function test_premium_activation_rejects_a_provider_amount_mismatch(): void
    {
        $owner = User::create(['username' => 'owner', 'type' => 'certifying']);
        $payment = Payment::create([
            'order_number' => 'PREMIUM-2', 'amount' => 10, 'currency' => 'USD',
            'premium_plan_amount' => 10, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 1,
            'type' => 1, 'status' => 0, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $owner->id, 'user_id' => $owner->id,
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/check/PREMIUM-2' => Http::response(['code' => '0', 'transaction' => ['orderNumber' => 'PREMIUM-2', 'status' => 0, 'amount' => 1, 'currency' => 'USD']])]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/payment/store', ['orderNumber' => 'PREMIUM-2', 'status' => 0])->assertUnprocessable();
        $this->assertSame('certifying', $owner->fresh()->type);
        $this->assertNull($payment->fresh()->premium_activated_at);
    }

    public function test_completed_premium_payment_is_processed_by_the_existing_payment_job(): void
    {
        $owner = User::create(['username' => 'owner']);
        $payment = Payment::create([
            'order_number' => 'PREMIUM-3', 'amount' => 20, 'currency' => 'USD',
            'premium_plan_amount' => 20, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 3,
            'type' => 1, 'status' => 0, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $owner->id, 'user_id' => $owner->id,
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/check/PREMIUM-3' => Http::response(['code' => '0', 'transaction' => ['orderNumber' => 'PREMIUM-3', 'status' => 0, 'amount' => 20, 'currency' => 'USD']])]);

        $this->artisan('payments:process-completed')->assertExitCode(0);

        $this->assertSame('certified', $owner->fresh()->type);
        $this->assertNotNull($payment->fresh()->premium_activated_at);
        $this->assertSame('payment_successful', AdminNotification::query()->firstOrFail()->type);
    }

    public function test_callback_alone_cannot_activate_premium_without_provider_confirmation(): void
    {
        $owner = User::create(['username' => 'owner', 'type' => 'certifying', 'premium_duration_months' => 1]);
        $payment = Payment::create([
            'order_number' => 'PREMIUM-UNCONFIRMED', 'amount' => 10, 'currency' => 'USD',
            'premium_plan_amount' => 10, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 1,
            'type' => 1, 'status' => Payment::STATUS_PENDING, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $owner->id, 'user_id' => $owner->id,
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/check/PREMIUM-UNCONFIRMED' => Http::response([
            'code' => '0',
            'transaction' => ['orderNumber' => 'PREMIUM-UNCONFIRMED', 'status' => Payment::STATUS_PENDING, 'amount' => 10, 'currency' => 'USD'],
        ])]);

        $this->postJson('/api/payment/store', ['orderNumber' => 'PREMIUM-UNCONFIRMED', 'status' => 0])->assertUnprocessable();
        $this->artisan('payments:process-completed')->assertExitCode(0);

        $this->assertSame('certifying', $owner->fresh()->type);
        $this->assertNull($payment->fresh()->premium_activated_at);
        $this->assertSame(0, AdminNotification::query()->count());
    }

    public function test_confirmed_failed_payment_clears_certifying_status(): void
    {
        $owner = User::create(['username' => 'owner', 'type' => 'certifying', 'premium_duration_months' => 1]);
        Payment::create([
            'order_number' => 'PREMIUM-FAILED', 'amount' => 10, 'currency' => 'USD',
            'premium_plan_amount' => 10, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 1,
            'type' => 1, 'status' => Payment::STATUS_PENDING, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $owner->id, 'user_id' => $owner->id,
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/check/PREMIUM-FAILED' => Http::response([
            'code' => '0',
            'transaction' => ['orderNumber' => 'PREMIUM-FAILED', 'status' => Payment::STATUS_FAILED, 'amount' => 10, 'currency' => 'USD'],
        ])]);

        $this->postJson('/api/payment/store', ['orderNumber' => 'PREMIUM-FAILED', 'status' => Payment::STATUS_FAILED])->assertOk();

        $this->assertSame('uncertified', $owner->fresh()->type);
        $this->assertNull($owner->fresh()->premium_started_at);
        $this->assertNull($owner->fresh()->premium_expires_at);
        $this->assertSame('payment_failed', AdminNotification::query()->firstOrFail()->type);
        $this->assertSame($owner->id, AdminNotification::query()->firstOrFail()->to_user_id);
    }

    public function test_paid_plan_can_be_activated_after_its_pricing_is_removed(): void
    {
        $owner = User::create(['username' => 'owner', 'type' => 'certifying']);
        $payment = Payment::create([
            'order_number' => 'PREMIUM-SNAPSHOT', 'amount' => 10, 'currency' => 'USD',
            'premium_plan_amount' => 10, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 1,
            'type' => 1, 'status' => 0, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $owner->id, 'user_id' => $owner->id,
        ]);
        Pricing::query()->delete();
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/check/PREMIUM-SNAPSHOT' => Http::response([
            'code' => '0',
            'transaction' => ['orderNumber' => 'PREMIUM-SNAPSHOT', 'status' => 0, 'amount' => 10, 'currency' => 'USD'],
        ])]);

        app(PremiumService::class)->completePayment($payment);

        $this->assertSame('certified', $owner->fresh()->type);
        $this->assertNotNull($payment->fresh()->premium_activated_at);
    }

    public function test_members_cannot_certify_themselves_through_profile_endpoints(): void
    {
        $user = User::create(['username' => 'member']);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/user/{$user->id}", ['type' => 'certified'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
        $this->putJson("/api/v1/user/{$user->id}", ['premium_expires_at' => now()->addYear()->toDateTimeString()])
            ->assertUnprocessable();
        $this->patchJson("/api/v1/user/{$user->id}/type", ['type' => 'certified'])
            ->assertUnprocessable();
        $this->assertSame('uncertified', $user->fresh()->type);

        $this->assertNull($user->fresh()->premium_expires_at);
    }

    public function test_members_cannot_rewrite_premium_payment_snapshots(): void
    {
        $owner = User::create(['username' => 'owner']);
        $payment = Payment::create([
            'order_number' => 'PREMIUM-LOCKED', 'amount' => 10, 'currency' => 'USD',
            'premium_plan_amount' => 10, 'premium_plan_currency' => 'USD', 'premium_duration_months' => 1,
            'type' => 1, 'status' => Payment::STATUS_PENDING, 'reason' => 'user_certfied', 'entity' => 'user',
            'entity_id' => $owner->id, 'user_id' => $owner->id,
        ]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/payment/{$payment->id}", ['premium_plan_amount' => 1, 'status' => 0])
            ->assertMethodNotAllowed();
        $this->deleteJson("/api/v1/payment/{$payment->id}")
            ->assertMethodNotAllowed();
        $this->assertSame('10.00', $payment->fresh()->premium_plan_amount);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_premium_migrations_add_missing_columns_and_preserve_imported_columns(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('files');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('premium_started_at')->nullable();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('premium_duration_months')->nullable();
        });

        $usersMigration = require database_path('migrations/2026_10_31_032417_add_premium_period_to_users_table.php');
        $paymentsMigration = require database_path('migrations/2026_10_31_032832_add_premium_plan_snapshot_to_payments_table.php');
        $usersMigration->up();
        $usersMigration->up();
        $paymentsMigration->up();
        $paymentsMigration->up();

        $this->assertTrue(Schema::hasColumns('users', ['premium_started_at', 'premium_expires_at', 'premium_duration_months']));
        $this->assertTrue(Schema::hasIndex('users', 'users_premium_expires_at_index'));
        $this->assertTrue(Schema::hasColumns('payments', ['premium_duration_months', 'premium_plan_amount', 'premium_plan_currency', 'premium_activated_at']));

        $usersMigration->down();
        $paymentsMigration->down();
        $this->assertTrue(Schema::hasColumn('users', 'premium_expires_at'));
    }
}
