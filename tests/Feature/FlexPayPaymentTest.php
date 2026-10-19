<?php

namespace Tests\Feature;

use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\FlexPayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class FlexPayPaymentTest extends TestCase
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
        Schema::dropIfExists('payments');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->text('password')->nullable();
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
            $table->timestamp('coins_credited_at')->nullable();
            $table->foreignId('user_id')->nullable();
        });

        Schema::enableForeignKeyConstraints();

        config([
            'services.flexpay.api_token' => 'Bearer test-token',
            'services.flexpay.merchant' => 'merchant-code',
            'services.flexpay.gateway_mobile' => 'https://flexpay.test/mobile',
            'services.flexpay.gateway_card' => 'https://flexpay.test/card',
        ]);
    }

    public function test_it_initiates_a_mobile_money_payment_and_stores_it_as_pending(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password']);
        Http::preventStrayRequests();
        Http::fake([
            'https://flexpay.test/mobile' => Http::response([
                'code' => '0',
                'message' => 'Push sent.',
                'orderNumber' => 'FLEX-1001',
            ]),
        ]);

        $result = app(FlexPayService::class)->initiate([
            'user_id' => $user->id,
            'type' => 1,
            'amount' => 25.50,
            'currency' => 'USD',
            'phone' => '243810000000',
            'reason' => 'product_sale',
            'entity' => 'cart',
            'entity_id' => 8,
        ]);

        $this->assertSame('FLEX-1001', $result['payment']->order_number);
        $this->assertSame(1, $result['payment']->status);
        $this->assertSame(1, Payment::query()->count());
        $this->assertMatchesRegularExpression('/^REF-\d{8}-'.$user->id.'$/', $result['payment']->reference);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://flexpay.test/mobile'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['merchant'] === 'merchant-code'
                && $request['type'] === 1
                && $request['phone'] === '243810000000'
                && $request['callbackUrl'] === getApiURL().'/payment/store';
        });
    }

    public function test_it_initiates_a_bank_card_payment_with_its_redirect_urls(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password']);
        Http::preventStrayRequests();
        Http::fake([
            'https://flexpay.test/card' => Http::response([
                'code' => 0,
                'reference' => 'FLEX-REFERENCE',
                'provider_reference' => 'PROVIDER-REFERENCE',
                'orderNumber' => 'FLEX-1002',
                'url' => 'https://flexpay.test/checkout/FLEX-1002',
            ]),
        ]);

        $result = app(FlexPayService::class)->initiate([
            'user_id' => $user->id,
            'type' => 2,
            'amount' => 100,
            'currency' => 'CDF',
            'description' => 'Order payment',
            'approve_url' => 'https://untrusted.test/approved',
            'cancel_url' => 'https://untrusted.test/cancelled',
            'decline_url' => 'https://untrusted.test/declined',
        ]);

        $this->assertSame('FLEX-REFERENCE', $result['payment']->reference);
        $this->assertSame('PROVIDER-REFERENCE', $result['payment']->provider_reference);
        $this->assertSame('FLEX-1002', $result['payment']->order_number);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://flexpay.test/card'
            && $request['callback_url'] === getApiURL().'/payment/store'
            && $request['approve_url'] === getWebURL()."/paid/100/CDF/0/{$user->id}"
            && $request['cancel_url'] === getWebURL()."/paid/100/CDF/1/{$user->id}"
            && $request['decline_url'] === getWebURL()."/paid/100/CDF/2/{$user->id}");
    }

    public function test_it_does_not_store_a_payment_when_flexpay_rejects_the_request(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password']);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '1', 'message' => 'Rejected.'])]);

        try {
            app(FlexPayService::class)->initiate([
                'user_id' => $user->id,
                'type' => 1,
                'amount' => 25,
                'currency' => 'USD',
                'phone' => '243810000000',
            ]);

            $this->fail('A rejected FlexPay request must throw an exception.');
        } catch (RuntimeException) {
            $this->assertSame(0, Payment::query()->count());
        }
    }

    public function test_payment_casts_the_coin_credit_timestamp_as_a_date(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password']);
        $creditedAt = now()->startOfSecond();

        $payment = Payment::create([
            'order_number' => 'FLEX-CREDITED',
            'type' => 1,
            'user_id' => $user->id,
            'coins_credited_at' => $creditedAt,
        ]);

        $this->assertInstanceOf(Carbon::class, $payment->refresh()->coins_credited_at);
        $this->assertTrue($payment->refresh()->coins_credited_at->equalTo($creditedAt));
    }

    public function test_my_payments_lists_only_the_authenticated_users_payments_with_translated_labels(): void
    {
        $user = User::create(['email' => 'payer@example.com', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password']);

        Payment::create(['order_number' => 'MOBILE-1', 'type' => 1, 'status' => 0, 'reason' => 'coin_price', 'user_id' => $user->id]);
        Payment::create(['order_number' => 'CARD-1', 'type' => 2, 'status' => 2, 'reason' => 'user_certfied', 'user_id' => $user->id]);
        Payment::create(['order_number' => 'OTHER-1', 'type' => 1, 'status' => 1, 'reason' => 'gift', 'user_id' => $otherUser->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/user/{$user->id}/payments?lang=fr")
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.order_number', 'CARD-1')
            ->assertJsonPath('data.0.type_text', 'Carte Bancaire')
            ->assertJsonPath('data.0.status_text', 'Échoué')
            ->assertJsonPath('data.0.reason_text', 'Certification membre')
            ->assertJsonPath('data.1.type_text', 'Mobile Money')
            ->assertJsonPath('data.1.status_text', 'Effectué')
            ->assertJsonPath('data.1.reason_text', 'Achat des coins');

        $this->getJson("/api/v1/user/{$user->id}/payments?lang=en")
            ->assertOk()
            ->assertJsonPath('data.0.type_text', 'Bank Card')
            ->assertJsonPath('data.0.status_text', 'Failed')
            ->assertJsonPath('data.1.reason_text', 'Coin purchase');

        $this->getJson("/api/v1/user/{$otherUser->id}/payments?lang=fr")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_my_payments_returns_empty_list_for_existing_user_and_not_found_for_missing_user(): void
    {
        $user = User::create(['email' => 'empty@example.com', 'password' => 'password']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/user/{$user->id}/payments")
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/user/999999/payments')->assertNotFound();
    }

    public function test_payment_callback_includes_translated_labels(): void
    {
        $user = User::create(['email' => 'callback@example.com', 'password' => 'password']);
        $payment = Payment::create([
            'order_number' => 'CALLBACK-1',
            'type' => 1,
            'status' => 1,
            'reason' => 'media_create',
            'user_id' => $user->id,
        ]);

        $this->postJson('/api/payment/store?lang=fr', [
            'orderNumber' => $payment->order_number,
            'status' => 0,
        ])->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.type_text', 'Mobile Money')
            ->assertJsonPath('data.status_text', 'Effectué')
            ->assertJsonPath('data.reason_text', 'Création de média');
    }

    public function test_payment_check_and_details_include_translated_labels(): void
    {
        $user = User::create(['email' => 'check@example.com', 'password' => 'password']);
        $payment = Payment::create([
            'order_number' => 'CHECK-1',
            'type' => 2,
            'status' => 1,
            'reason' => 'ad',
            'user_id' => $user->id,
        ]);

        config(['services.flexpay.gateway_check' => 'https://flexpay.test/check']);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/check/CHECK-1' => Http::response([
            'code' => '0',
            'transaction' => ['status' => 2],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/payment/{$payment->id}/check?lang=en")
            ->assertOk()
            ->assertJsonPath('data.type_text', 'Bank Card')
            ->assertJsonPath('data.status_text', 'Failed')
            ->assertJsonPath('data.reason_text', 'Advertisement');

        $this->getJson("/api/v1/payment/{$payment->id}?lang=fr")
            ->assertOk()
            ->assertJsonPath('data.type_text', 'Carte Bancaire')
            ->assertJsonPath('data.status_text', 'Échoué')
            ->assertJsonPath('data.reason_text', 'Annonce');
    }

    public function test_payment_resource_covers_all_reasons_and_statuses_in_both_languages(): void
    {
        $reasons = [
            'media_create' => ['Création de média', 'Media creation'],
            'media_boost' => ['Boost de média', 'Media boost'],
            'gift' => ['Envoi de cadeau', 'Gift sending'],
            'product_sale' => ['Vente de produit/service', 'Product/service sale'],
            'user_certfied' => ['Certification membre', 'Member certification'],
            'ad' => ['Annonce', 'Advertisement'],
            'coin_price' => ['Achat des coins', 'Coin purchase'],
        ];

        foreach ($reasons as $reason => [$french, $english]) {
            $payment = new Payment(['type' => 1, 'status' => 1, 'reason' => $reason]);

            app()->setLocale('fr');
            $this->assertSame($french, PaymentResource::labels($payment)['reason_text']);
            app()->setLocale('en');
            $this->assertSame($english, PaymentResource::labels($payment)['reason_text']);
        }

        app()->setLocale('fr');
        $this->assertSame('En cours', PaymentResource::labels(new Payment(['type' => 1, 'status' => 1]))['status_text']);
        $this->assertNull(PaymentResource::labels(new Payment(['type' => 3, 'status' => 3, 'reason' => 'other']))['reason_text']);
    }
}
