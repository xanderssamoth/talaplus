<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiResourceController;
use App\Http\Resources\Api\ApiResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Client\Request;
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
            $table->foreignId('user_id')->nullable();
        });

        Schema::enableForeignKeyConstraints();

        config([
            'services.flexpay.api_token' => 'test-token',
            'services.flexpay.merchant' => 'merchant-code',
            'services.flexpay.gateway_mobile' => 'https://flexpay.test/mobile',
            'services.flexpay.gateway_card_v2' => 'https://flexpay.test/card',
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

        $result = $this->paymentController()->launch([
            'user_id' => $user->id,
            'type' => 1,
            'amount' => 25.50,
            'currency' => 'USD',
            'phone' => '243810000000',
            'callback_url' => 'https://app.test/api/v1/payment/callback',
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
                && $request['callbackUrl'] === 'https://app.test/api/v1/payment/callback';
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

        $result = $this->paymentController()->launch([
            'user_id' => $user->id,
            'type' => 2,
            'amount' => 100,
            'currency' => 'CDF',
            'description' => 'Order payment',
            'callback_url' => 'https://app.test/api/v1/payment/callback',
            'approve_url' => 'https://app.test/payment/approved',
            'cancel_url' => 'https://app.test/payment/cancelled',
            'decline_url' => 'https://app.test/payment/declined',
        ]);

        $this->assertSame('FLEX-REFERENCE', $result['payment']->reference);
        $this->assertSame('PROVIDER-REFERENCE', $result['payment']->provider_reference);
        $this->assertSame('FLEX-1002', $result['payment']->order_number);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://flexpay.test/card'
            && $request['callback_url'] === 'https://app.test/api/v1/payment/callback'
            && $request['approve_url'] === 'https://app.test/payment/approved'
            && $request['cancel_url'] === 'https://app.test/payment/cancelled'
            && $request['decline_url'] === 'https://app.test/payment/declined');
    }

    public function test_it_does_not_store_a_payment_when_flexpay_rejects_the_request(): void
    {
        $user = User::create(['email' => 'customer@example.com', 'password' => 'password']);
        Http::preventStrayRequests();
        Http::fake(['https://flexpay.test/mobile' => Http::response(['code' => '1', 'message' => 'Rejected.'])]);

        try {
            $this->paymentController()->launch([
                'user_id' => $user->id,
                'type' => 1,
                'amount' => 25,
                'currency' => 'USD',
                'phone' => '243810000000',
                'callback_url' => 'https://app.test/api/v1/payment/callback',
            ]);

            $this->fail('A rejected FlexPay request must throw an exception.');
        } catch (RuntimeException) {
            $this->assertSame(0, Payment::query()->count());
        }
    }

    private function paymentController(): FlexPayTestController
    {
        return new FlexPayTestController;
    }
}

class FlexPayTestController extends ApiResourceController
{
    protected string $modelClass = Payment::class;

    protected string $resourceClass = ApiResource::class;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{payment: Payment, response: array<string, mixed>}
     */
    public function launch(array $attributes): array
    {
        return $this->initiateFlexPayPayment($attributes);
    }
}
