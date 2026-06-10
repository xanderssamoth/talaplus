<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminResourceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

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

    public function test_pricing_form_uses_usd_hidden_currency_and_cost_label(): void
    {
        $view = app(AdminResourceController::class)->index('pricings');
        $fields = collect($view->getData()['config']['fields'])->keyBy('name');

        $this->assertSame('Coût (en USD)', $fields['pricing_cost']['label']);
        $this->assertSame('hidden', $fields['currency']['type']);
        $this->assertSame('USD', $fields['currency']['value']);
    }

    public function test_video_files_can_be_uploaded_to_media_columns(): void
    {
        Storage::fake('s3');
        $this->createMediaTables();

        $admin = User::factory()->create();
        $owner = User::factory()->create(['currency' => 'USD']);

        $response = $this->actingAs($admin)->post('/videos', [
            'media_title' => ['fr' => 'Film'],
            'media_description' => ['fr' => 'Description'],
            'type' => 'film_series',
            'price' => 5,
            'user_id' => $owner->id,
            'media_url' => UploadedFile::fake()->create('film.mp4', 256, 'video/mp4'),
            'cover_url' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('item.price_display', '5,00 USD');

        $media = \DB::table('medias')->first();
        $this->assertStringContainsString('medias/videos/', $media->media_url);
        $this->assertStringContainsString('medias/covers/', $media->cover_url);
    }

    public function test_video_form_uses_episode_and_content_creator_selects(): void
    {
        $this->createMediaTables();
        $this->createRoleTables();

        $creator = User::factory()->create(['firstname' => 'Paul', 'lastname' => 'Kiese']);
        $roleId = (int) \DB::table('roles')->insertGetId([
            'role_name' => json_encode(['fr' => 'Créateur de contenu']),
            'role_description' => json_encode(['fr' => 'Personne qui envoie des vidéos à publier sur la plateforme']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $creator->id,
            'is_selected' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seriesId = $this->insertMedia($creator->id);

        $view = app(AdminResourceController::class)->index('videos');
        $fields = collect($view->getData()['config']['fields'])->keyBy('name');

        $this->assertSame('Est un épisode de', $fields['belongs_to']['label']);
        $this->assertSame('select', $fields['belongs_to']['type']);
        $this->assertArrayHasKey((string) $seriesId, $fields['belongs_to']['options']);
        $this->assertSame('Appartient à', $fields['user_id']['label']);
        $this->assertSame('select', $fields['user_id']['type']);
        $this->assertSame('Paul Kiese', $fields['user_id']['options'][(string) $creator->id]);
    }

    public function test_video_uploads_are_required_when_creating_media(): void
    {
        $this->createMediaTables();

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post('/videos', [
                'media_title' => ['fr' => 'Film'],
                'type' => 'film_series',
                'price' => 5,
            ])
            ->assertSessionHasErrors(['media_url', 'cover_url']);
    }

    public function test_app_info_can_be_saved_with_images(): void
    {
        Storage::fake('s3');
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

        \DB::table('notifications')->insert([
            'type' => 'comment_sent',
            'is_read' => 0,
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'comment_id' => 123,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($recipient)
            ->getJson('/notifications/data')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.1.message_display', 'Sarah Mbala a envoyé une nouvelle vidéo')
            ->assertJsonPath('items.0.url', null);

        $this->assertSame(route('videos.index'), $response->json('items.1.url'));

        $this->actingAs($recipient)
            ->patchJson("/notifications/{$visibleNotificationId}/read")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'id' => $visibleNotificationId,
            'is_read' => 1,
        ]);
    }

    public function test_dashboard_displays_requested_statistics_and_recent_blocks(): void
    {
        $this->createMediaTables();
        $this->createCategoryTables();
        $this->createRoleTables();
        $this->createPaymentTables();

        $admin = User::factory()->create();
        $memberRoleId = (int) \DB::table('roles')->insertGetId([
            'role_name' => json_encode(['fr' => 'Membre']),
            'role_description' => json_encode(['fr' => 'Utilisateur de la plateforme']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $members = User::factory()->count(6)->create();
        foreach ($members as $member) {
            \DB::table('role_user')->insert([
                'role_id' => $memberRoleId,
                'user_id' => $member->id,
                'is_selected' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        \DB::table('categories')->insert([
            ['category_name' => json_encode(['fr' => 'Films']), 'for_type' => 'film_series', 'created_at' => now(), 'updated_at' => now()],
            ['category_name' => json_encode(['fr' => 'Musique']), 'for_type' => 'music', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('medias')->insert([
            ['media_title' => json_encode(['fr' => 'Publiée']), 'type' => 'film_series', 'is_shared' => 1, 'user_id' => $members[0]->id, 'created_at' => now(), 'updated_at' => now()],
            ['media_title' => json_encode(['fr' => 'Non publiée']), 'type' => 'film_series', 'is_shared' => 0, 'user_id' => $members[1]->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('products')->insert([
            ['product_name' => 'Produit publié', 'is_shared' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['product_name' => 'Produit non publié', 'is_shared' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('payments')->insert([
            ['status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['status' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['status' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['status' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Vidéos publiées')
            ->assertSee('Statistiques des paiements')
            ->assertSee('5 utilisateurs les plus récents')
            ->assertSee('col-xxl-4', false)
            ->assertSee(route('videos.index'))
            ->assertSee(route('products.index'))
            ->assertSee(route('users.index'))
            ->assertSee(route('categories.index'))
            ->assertSee("type: 'line'", false)
            ->assertSee('Paiement réussi')
            ->assertSee('dashboard-toggle-shared', false)
            ->assertSee('dashboard-change-status', false);

        $this->assertSame([1, 2, 1], array_values($response->viewData('paymentStats')));
        $this->assertSame(5, $response->viewData('recentUsers')->count());
    }

    public function test_dashboard_compact_numbers_are_human_readable(): void
    {
        $controller = app(AdminResourceController::class);
        $method = new \ReflectionMethod($controller, 'compactDashboardNumber');
        $method->setAccessible(true);

        $this->assertSame('999', $method->invoke($controller, 999));
        $this->assertSame('Plus de 1k', $method->invoke($controller, 1000));
        $this->assertSame('Plus de 10k', $method->invoke($controller, 10000));
        $this->assertSame('Plus de 100k', $method->invoke($controller, 100000));
        $this->assertSame('Plus de 1M', $method->invoke($controller, 1000000));
        $this->assertSame('Plus de 100M', $method->invoke($controller, 999999999));
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
        $this->assertSame([
            'film_series',
            'comedy',
            'music',
            'education',
            'business',
            'crafts_diy',
            'sports',
            'documentary',
            'product',
            'service',
        ], array_keys($fields['for_type']['options']));
    }

    public function test_shareable_resource_tables_use_bootstrap_switches(): void
    {
        $html = app(AdminResourceController::class)->index('videos')->render();

        $this->assertStringContainsString('form-check-input toggle-shared', $html);
        $this->assertStringContainsString('bi-three-dots-vertical', $html);
        $this->assertStringContainsString('>Voir<', $html);
        $this->assertStringContainsString('>Modifier<', $html);
        $this->assertStringContainsString('>Supprimer<', $html);
        $this->assertStringContainsString('<th class="text-end"></th>', $html);
    }

    public function test_pricing_and_about_forms_use_wider_columns_and_full_width_language_fields(): void
    {
        $pricingHtml = app(AdminResourceController::class)->index('pricings')->render();
        $aboutHtml = app(AdminResourceController::class)->index('abouts')->render();

        $this->assertStringContainsString('class="col-lg-6"', $pricingHtml);
        $this->assertStringContainsString('name="descriptions[__INDEX__][description_content][fr]" rows="3"', $pricingHtml);
        $this->assertStringContainsString('class="col-lg-6"', $aboutHtml);
        $this->assertStringContainsString('name="titles[__TITLE__][contents][__CONTENT__][subtitle][fr]"', $aboutHtml);
        $this->assertStringContainsString('name="titles[__TITLE__][contents][__CONTENT__][content][fr]" rows="3"', $aboutHtml);
        $this->assertStringContainsString('Sous-tiret', $aboutHtml);
        $this->assertStringNotContainsString('Depend du tiret ID', $aboutHtml);
    }

    public function test_about_form_saves_nested_sub_dashes_with_parent_belongs_to(): void
    {
        $this->createAboutTables();

        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/abouts', [
            'subject' => ['fr' => 'Conditions'],
            'subject_description' => ['fr' => 'Description'],
            'status' => 'selected',
            'titles' => [
                [
                    'title' => ['fr' => 'Titre 1'],
                    'alias' => 'titre-1',
                    'contents' => [
                        [
                            'subtitle' => ['fr' => 'Sous-titre'],
                            'content' => ['fr' => 'Contenu'],
                            'dashes' => [
                                [
                                    'dash_content' => ['fr' => 'Tiret 1'],
                                    'sub_dashes' => [
                                        ['dash_content' => ['fr' => 'Sous-tiret 1']],
                                        ['dash_content' => ['fr' => 'Sous-tiret 2']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $parent = \DB::table('about_dashes')->where('dash_content->fr', 'Tiret 1')->first();
        $this->assertNotNull($parent);
        $this->assertDatabaseHas('about_dashes', [
            'dash_content->fr' => 'Sous-tiret 1',
            'belongs_to' => $parent->id,
        ]);
        $this->assertDatabaseHas('about_dashes', [
            'dash_content->fr' => 'Sous-tiret 2',
            'belongs_to' => $parent->id,
        ]);
    }

    public function test_product_form_uses_category_and_member_selects(): void
    {
        $this->createCategoryTables();
        $this->createRoleTables();

        $member = User::factory()->create(['firstname' => 'Anne', 'lastname' => 'Lutumba']);
        $roleId = (int) \DB::table('roles')->insertGetId([
            'role_name' => json_encode(['fr' => 'Membre']),
            'role_description' => json_encode(['fr' => 'Personne qui consulte ou commente les posts et les vidéos ; et qui commande des produits']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $member->id,
            'is_selected' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productCategoryId = (int) \DB::table('categories')->insertGetId([
            'category_name' => json_encode(['fr' => 'Boutique']),
            'for_type' => 'product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $serviceCategoryId = (int) \DB::table('categories')->insertGetId([
            'category_name' => json_encode(['fr' => 'Services']),
            'for_type' => 'service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $view = app(AdminResourceController::class)->index('products');
        $fields = collect($view->getData()['config']['fields'])->keyBy('name');

        $this->assertSame('Catégorie', $fields['category_id']['label']);
        $this->assertSame('select', $fields['category_id']['type']);
        $this->assertSame('product', $fields['category_id']['option_attrs'][(string) $productCategoryId]['data-for-type']);
        $this->assertSame('service', $fields['category_id']['option_attrs'][(string) $serviceCategoryId]['data-for-type']);
        $this->assertSame('Appartient à', $fields['user_id']['label']);
        $this->assertSame('Anne Lutumba', $fields['user_id']['options'][(string) $member->id]);
    }

    public function test_users_form_creates_partner_role_and_excludes_it_from_user_role_select(): void
    {
        $this->createRoleTables();

        $view = app(AdminResourceController::class)->index('users');
        $config = $view->getData()['config'];
        $fields = collect($config['fields'])->keyBy('name');
        $partnerRoleId = (string) $config['partner_role_id'];

        $this->assertDatabaseHas('roles', [
            'role_name->fr' => 'Partenaire',
            'role_description->fr' => 'Personne qui paie pour faire la publicité sur la plateforme.',
        ]);
        $this->assertArrayHasKey('user', $config['user_modes']);
        $this->assertArrayHasKey('partner', $config['user_modes']);
        $this->assertArrayNotHasKey($partnerRoleId, $fields['role_id']['options']);
        $this->assertArrayHasKey($partnerRoleId, $config['role_filter_options']);
        $this->assertArrayNotHasKey('avatar_url', $fields->all());
        $this->assertArrayNotHasKey('cover_url', $fields->all());
        $this->assertArrayHasKey('password_confirmation', $fields->all());
    }

    public function test_user_avatar_base64_is_saved_to_s3(): void
    {
        Storage::fake('s3');

        $admin = User::factory()->create();
        $image = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $response = $this->actingAs($admin)->postJson('/users', [
            'firstname' => 'Mireille',
            'lastname' => 'Kanza',
            'email' => 'mireille@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'avatar_base64' => $image,
        ]);

        $response->assertOk();

        $createdUser = User::query()->where('email', 'mireille@example.com')->firstOrFail();
        $this->assertStringContainsString('users/avatars/', $createdUser->avatar_url);
    }

    public function test_category_icons_are_presented_with_their_color(): void
    {
        $this->createCategoryTables();

        $admin = User::factory()->create();
        $createdAt = now()->setDate(2026, 5, 27)->setTime(14, 30, 12);

        \DB::table('categories')->insert([
            'category_name' => json_encode(['fr' => 'Films']),
            'category_description' => json_encode(['fr' => 'Films et séries']),
            'icon' => 'film',
            'color' => '#ff0033',
            'for_type' => 'film_series',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->actingAs($admin)
            ->getJson('/categories/data')
            ->assertOk()
            ->assertJsonPath('group_by', 'for_type')
            ->assertJsonPath('items.0.category_name_display', 'Films')
            ->assertJsonPath('items.0.icon_display', 'film')
            ->assertJsonPath('items.0.icon_preview.class', 'fa-solid fa-film')
            ->assertJsonPath('items.0.icon_preview.color', '#ff0033')
            ->assertJsonPath('items.0.created_at_detail_display', 'Le 27-05-26 à 14:30:12');
    }

    public function test_users_can_be_filtered_by_role_and_status_can_be_changed(): void
    {
        $this->createRoleTables();

        $admin = User::factory()->create();
        $user = User::factory()->create(['status' => 'created']);
        $roleId = (int) \DB::table('roles')->insertGetId([
            'role_name' => json_encode(['fr' => 'Administrateur']),
            'role_description' => json_encode(['fr' => 'Gestion']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'is_selected' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/users/data')
            ->assertOk()
            ->assertJsonPath('items.0.role_id', $roleId)
            ->assertJsonPath('items.0.role_id_display', 'Administrateur')
            ->assertJsonPath('items.0.status_display', 'Créé');

        $this->actingAs($admin)
            ->patchJson("/users/{$user->id}/status", ['status' => 'activated'])
            ->assertOk()
            ->assertJsonPath('item.status', 'activated')
            ->assertJsonPath('item.status_display', 'Activé');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'activated',
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

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->longText('comment_content')->nullable();
            $table->unsignedBigInteger('answered_for')->nullable();
            $table->string('type')->default('post');
            $table->string('for_entity')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id')->nullable();
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

    private function createRoleTables(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->json('role_name');
            $table->json('role_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
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

    private function createAboutTables(): void
    {
        Schema::create('about_subjects', function (Blueprint $table): void {
            $table->id();
            $table->json('subject')->nullable();
            $table->json('subject_description');
            $table->string('status')->default('rejected');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('about_titles', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->string('alias')->nullable();
            $table->foreignId('about_subject_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('about_contents', function (Blueprint $table): void {
            $table->id();
            $table->json('subtitle')->nullable();
            $table->json('content');
            $table->foreignId('about_title_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('about_dashes', function (Blueprint $table): void {
            $table->id();
            $table->json('dash_content');
            $table->foreignId('belongs_to')->nullable();
            $table->foreignId('about_content_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createPaymentTables(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->integer('status')->nullable();
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
