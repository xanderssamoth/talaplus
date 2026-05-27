<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminResourceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    public function test_app_info_can_be_saved_with_images(): void
    {
        Storage::fake('public');
        $this->createAppInfoTables();

        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/app-infos', [
            'comment_content' => 'Presentation des fonctionnalites video.',
            'for_entity' => 'media',
            'files' => [
                UploadedFile::fake()->image('video-feature.jpg'),
                UploadedFile::fake()->image('player-preview.png'),
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('comments', [
            'comment_content' => 'Presentation des fonctionnalites video.',
            'type' => 'app_info',
            'for_entity' => 'media',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseCount('files', 2);
        $this->assertDatabaseHas('files', [
            'file_type' => 'photo',
            'user_id' => $admin->id,
        ]);
    }

    public function test_notifications_are_scoped_to_current_user_and_can_be_marked_read(): void
    {
        $this->createMediaTables();

        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();
        $sender = User::factory()->create([
            'firstname' => 'Sarah',
            'lastname' => 'Mbala',
        ]);
        $mediaId = $this->insertMedia($sender->id);

        $visibleNotificationId = (int) \DB::table('notifications')->insertGetId([
            'type' => 'media_created',
            'is_read' => 0,
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'media_id' => $mediaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('notifications')->insert([
            'type' => 'media_created',
            'is_read' => 0,
            'from_user_id' => $sender->id,
            'to_user_id' => $otherUser->id,
            'media_id' => $mediaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($recipient)
            ->getJson('/notifications/data')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.message_display', 'Sarah Mbala a envoyé une nouvelle vidéo')
            ->assertJsonPath('items.0.url', "https://tempor.silasmas.com/videos/{$mediaId}");

        $this->actingAs($recipient)
            ->patchJson("/notifications/{$visibleNotificationId}/read")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'id' => $visibleNotificationId,
            'is_read' => 1,
        ]);
    }

    public function test_category_page_uses_short_table_columns_and_full_form_labels(): void
    {
        $view = app(AdminResourceController::class)->index('categories');
        $config = $view->getData()['config'];
        $fields = collect($config['fields'])->keyBy('name');

        $this->assertSame(['category_name', 'category_description', 'icon', 'created_at'], $config['columns']);
        $this->assertSame('Nom de catégorie', $fields['category_name']['label']);
        $this->assertSame('Description de catégorie', $fields['category_description']['label']);
        $this->assertSame('Type concerné', $fields['for_type']['label']);
        $this->assertSame('Nom', $config['table_labels']['category_name']);
        $this->assertSame('Description', $config['table_labels']['category_description']);
    }

    public function test_category_icons_are_presented_with_their_color(): void
    {
        $this->createCategoryTables();

        $admin = User::factory()->create();

        \DB::table('categories')->insert([
            'category_name' => json_encode(['fr' => 'Films']),
            'category_description' => json_encode(['fr' => 'Films et séries']),
            'icon' => 'film',
            'color' => '#ff0033',
            'for_type' => 'film_series',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/categories/data')
            ->assertOk()
            ->assertJsonPath('group_by', 'for_type')
            ->assertJsonPath('items.0.category_name_display', 'Films')
            ->assertJsonPath('items.0.icon_display', 'film')
            ->assertJsonPath('items.0.icon_preview.class', 'fa-solid fa-film')
            ->assertJsonPath('items.0.icon_preview.color', '#ff0033');
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
            $table->foreignId('comment_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createCategoryTables(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->json('category_name')->nullable();
            $table->json('category_description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('for_type')->nullable();
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

    private function createAppInfoTables(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->longText('comment_content')->nullable();
            $table->foreignId('answered_for')->nullable();
            $table->string('type')->default('post');
            $table->string('for_entity')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->longText('file_description')->nullable();
            $table->string('file_type')->default('photo');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('comment_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('message_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
