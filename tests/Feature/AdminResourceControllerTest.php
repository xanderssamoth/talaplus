<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_can_be_published_and_owner_is_notified(): void
    {
        $this->createMediaTables();

        $admin = User::factory()->create();
        $owner = User::factory()->create(['firstname' => 'Grace']);

        $mediaId = $this->insertMedia($owner->id);

        $response = $this->actingAs($admin)->patchJson("/videos/{$mediaId}/shared");

        $response->assertOk();
        $this->assertDatabaseHas('medias', [
            'id' => $mediaId,
            'is_shared' => 1,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => 'media_accepted',
            'to_user_id' => $owner->id,
            'media_id' => $mediaId,
        ]);
    }

    public function test_pricing_can_be_saved_with_descriptions(): void
    {
        $this->createPricingTables();

        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/pricings', [
            'pricing_name' => [
                'fr' => 'Boost video',
                'en' => 'Video boost',
                'ln' => 'Botomboli video',
            ],
            'pricing_type' => 'money',
            'reason' => 'media_boost',
            'pricing_cost' => 10,
            'currency' => 'USD',
            'descriptions' => [
                [
                    'description_title' => [
                        'fr' => 'Visibilite',
                        'en' => 'Visibility',
                        'ln' => 'Komonana',
                    ],
                    'description_content' => [
                        'fr' => 'Met la video en avant.',
                        'en' => 'Highlights the video.',
                        'ln' => 'Elakisi video mingi.',
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pricings', [
            'pricing_name->fr' => 'Boost video',
            'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('pricing_descriptions', [
            'description_title->fr' => 'Visibilite',
        ]);
    }

    private function createMediaTables(): void
    {
        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->text('media_title')->nullable();
            $table->longText('media_description')->nullable();
            $table->text('media_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('author_names')->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('for_youth')->default(false);
            $table->unsignedBigInteger('belongs_to')->nullable();
            $table->string('type')->default('film_series');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->foreignId('from_user_id')->nullable();
            $table->foreignId('to_user_id')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createPricingTables(): void
    {
        Schema::create('pricings', function (Blueprint $table): void {
            $table->id();
            $table->json('pricing_name');
            $table->string('pricing_type')->default('money');
            $table->string('reason')->nullable();
            $table->decimal('pricing_cost', 12, 2)->nullable();
            $table->string('currency', 45)->nullable();
            $table->text('image_url')->nullable();
            $table->string('icon', 45)->nullable();
            $table->string('color', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pricing_descriptions', function (Blueprint $table): void {
            $table->id();
            $table->json('description_title');
            $table->json('description_content')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('pricing_id');
        });
    }

    private function insertMedia(int $ownerId): int
    {
        return (int) \DB::table('medias')->insertGetId([
            'media_title' => json_encode(['fr' => 'Video test']),
            'media_description' => json_encode(['fr' => 'Description']),
            'type' => 'film_series',
            'price' => 0,
            'is_shared' => 0,
            'user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
