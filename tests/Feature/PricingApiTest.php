<?php

namespace Tests\Feature;

use App\Models\Pricing;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('pricings');
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
    }

    public function test_index_groups_pricings_by_reason_and_excludes_deleted_rows(): void
    {
        Pricing::create([
            'pricing_name' => ['fr' => '1 mois', 'en' => '1 month', 'ln' => 'Sanza 1'],
            'pricing_type' => 'money', 'reason' => 'user_certfied',
            'premium_duration_months' => 1, 'pricing_cost' => 10, 'currency' => 'USD',
        ]);
        Pricing::create([
            'pricing_name' => ['fr' => 'Boost', 'en' => 'Boost', 'ln' => 'Boost'],
            'pricing_type' => 'money', 'reason' => 'media_boost', 'pricing_cost' => 5, 'currency' => 'USD',
        ]);
        Pricing::create([
            'pricing_name' => ['fr' => 'Supprimé'], 'pricing_type' => 'money',
            'reason' => 'ad', 'pricing_cost' => 3, 'currency' => 'USD',
        ])->delete();

        $this->getJson('/api/v1/pricing')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.user_certfied.0.premium_duration_months', 1)
            ->assertJsonPath('data.user_certfied.0.pricing_name.en', '1 month')
            ->assertJsonPath('data.media_boost.0.pricing_cost', '5.00')
            ->assertJsonMissingPath('data.ad');
    }

    public function test_find_by_reason_returns_only_matching_pricings_or_an_empty_list(): void
    {
        Pricing::create([
            'pricing_name' => ['fr' => '3 mois', 'en' => '3 months', 'ln' => 'basanza 3'],
            'pricing_type' => 'money', 'reason' => 'user_certfied',
            'premium_duration_months' => 3, 'pricing_cost' => 20, 'currency' => 'USD',
        ]);

        $this->getJson('/api/v1/pricing/reason/user_certfied')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.pricing_name.ln', 'basanza 3');

        $this->getJson('/api/v1/pricing/reason/media_boost')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);
    }

    public function test_guest_can_show_a_pricing_and_view_premium_plans(): void
    {
        $pricing = Pricing::create([
            'pricing_name' => ['fr' => '1 mois', 'en' => '1 month', 'ln' => 'Sanza 1'],
            'pricing_type' => 'money',
            'reason' => 'user_certfied',
            'premium_duration_months' => 1,
            'pricing_cost' => 10,
            'currency' => 'USD',
        ]);

        $this->getJson("/api/v1/pricing/{$pricing->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $pricing->id);

        $this->getJson('/api/v1/user/premium/pricing')
            ->assertOk()
            ->assertJsonPath('data.1.price', 10);
    }

    public function test_guest_cannot_change_pricing_descriptions(): void
    {
        $this->postJson('/api/v1/pricing-description', [])->assertUnauthorized();
    }

    public function test_duration_migration_keeps_an_imported_column(): void
    {
        $migration = require database_path('migrations/2026_11_07_072747_add_premium_duration_months_to_pricings_table.php');

        $migration->up();
        $this->assertTrue(Schema::hasColumn('pricings', 'premium_duration_months'));
        $migration->down();
        $this->assertTrue(Schema::hasColumn('pricings', 'premium_duration_months'));
    }

    public function test_pricing_mutations_are_not_exposed_to_members(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/pricing', [
            'pricing_name' => ['fr' => 'Premium gratuit'],
            'pricing_type' => 'money',
            'reason' => 'user_certfied',
            'premium_duration_months' => 1,
            'pricing_cost' => 0.01,
            'currency' => 'USD',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseCount('pricings', 0);
    }
}
