<?php

namespace Tests\Feature\Api;

use App\Http\Resources\Api\GiftTransactionResource;
use App\Http\Resources\Api\PaymentResource;
use App\Http\Resources\Api\PricingResource;
use App\Models\GiftTransaction;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSerializationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_receives_their_keys_and_new_user_columns_without_authentication_secrets(): void
    {
        $user = User::factory()->create([
            'username' => 'profile_owner',
            'api_key' => 'private-api-key',
            'api_token' => 'private-api-token',
            'referral_code_used' => 'REFERRAL123',
            'premium_duration_months' => 3,
            'premium_started_at' => now()->subDay(),
            'premium_expires_at' => now()->addMonths(3),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/user/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.api_key', 'private-api-key')
            ->assertJsonPath('data.api_token', 'private-api-token')
            ->assertJsonPath('data.referral_code_used', 'REFERRAL123')
            ->assertJsonPath('data.premium_duration_months', 3)
            ->assertJsonStructure(['data' => ['premium_started_at', 'premium_expires_at', 'promo_code_at', 'child_of']])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.two_factor_secret');
    }

    public function test_another_user_cannot_read_the_profile_keys(): void
    {
        $owner = User::factory()->create([
            'api_key' => 'private-api-key',
            'api_token' => 'private-api-token',
        ]);
        $viewer = User::factory()->create();

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/user/{$owner->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.api_key')
            ->assertJsonMissingPath('data.api_token')
            ->assertJsonMissingPath('data.child_lock_code');
    }

    public function test_successful_login_returns_the_issued_token_and_the_users_api_key(): void
    {
        User::factory()->create([
            'username' => 'profile_owner',
            'password' => 'correct-password',
            'status' => 'activated',
            'api_key' => 'private-api-key',
        ]);

        $response = $this->postJson('/api/v1/user/login', [
            'username' => 'profile_owner',
            'password' => 'correct-password',
        ])->assertOk()->assertJsonPath('data.api_key', 'private-api-key');

        $this->assertNotEmpty($response->json('data.api_token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_other_resources_include_the_recent_monetary_and_duration_columns(): void
    {
        $request = Request::create('/api/v1/pricing', 'GET');

        $pricing = (new Pricing)->forceFill([
            'pricing_name' => ['fr' => 'Trois mois', 'en' => 'Three months', 'ln' => 'Sanza misato'],
            'premium_duration_months' => 3,
        ]);
        $payment = (new Payment)->forceFill([
            'type' => 1,
            'status' => Payment::STATUS_PENDING,
            'coins_amount' => 100,
            'coin_unit_price' => '0.010000',
            'premium_duration_months' => 3,
            'premium_plan_amount' => '20.00',
            'premium_plan_currency' => 'USD',
        ]);
        $gift = (new GiftTransaction)->forceFill([
            'coins_amount' => 10,
            'gross_amount' => '0.10',
            'platform_fee_amount' => '0.03',
            'creator_earning_amount' => '0.07',
            'currency' => 'USD',
        ]);

        $this->assertSame(3, PricingResource::make($pricing)->resolve($request)['premium_duration_months']);
        $this->assertSame('Three months', PricingResource::make($pricing)->resolve($request)['pricing_name']['en']);
        $this->assertArrayHasKey('coin_unit_price', PaymentResource::make($payment)->resolve($request));
        $this->assertArrayHasKey('premium_plan_amount', PaymentResource::make($payment)->resolve($request));
        $this->assertArrayHasKey('creator_earning_amount', GiftTransactionResource::make($gift)->resolve($request));
        $this->assertArrayHasKey('platform_fee_amount', GiftTransactionResource::make($gift)->resolve($request));
    }
}
