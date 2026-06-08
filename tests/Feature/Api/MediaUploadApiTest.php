<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadApiTest extends TestCase
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

        foreach ([
            'notifications',
            'histories',
            'reactions',
            'files',
            'hashtag_media',
            'category_media',
            'hashtags',
            'categories',
            'role_user',
            'roles',
            'medias',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('christian_preference')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->json('role_name');
            $table->json('role_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
        });

        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->json('media_title')->nullable();
            $table->longText('media_description')->nullable();
            $table->integer('media_length')->nullable();
            $table->text('media_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('author_names')->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 12, 2);
            $table->boolean('for_youth')->default(false);
            $table->unsignedBigInteger('belongs_to')->nullable();
            $table->string('type')->default('music');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->json('category_name');
            $table->json('category_description')->nullable();
            $table->string('for_type');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('media_id');
            $table->timestamps();
        });

        Schema::create('hashtags', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword');
            $table->timestamps();
        });

        Schema::create('hashtag_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hashtag_id');
            $table->foreignId('media_id');
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->longText('file_description')->nullable();
            $table->string('file_type')->default('photo');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('media_id')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('histories', function (Blueprint $table): void {
            $table->id();
            $table->text('word')->nullable();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->foreignId('media_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_store_generates_media_and_cover_urls_from_uploaded_files(): void
    {
        Storage::fake('s3');

        $owner = User::create(['email' => 'owner@example.com', 'username' => 'owner', 'password' => 'password']);
        $admin = User::create(['email' => 'admin@example.com', 'username' => 'admin', 'password' => 'password']);
        $role = Role::create(['role_name' => ['fr' => 'Administrateur', 'en' => 'Administrator']]);
        $role->users()->attach($admin->id);
        $categoryId = \DB::table('categories')->insertGetId([
            'category_name' => json_encode(['fr' => 'Films']),
            'for_type' => 'film_series',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/api/v1/media', [
            'media_title' => 'Song',
            'media_description' => 'A new song',
            'media_file' => UploadedFile::fake()->create('song.mp4', 100, 'video/mp4'),
            'cover_file' => UploadedFile::fake()->image('cover.jpg'),
            'category_ids' => [$categoryId],
            'type' => 'music',
            'user_id' => $owner->id,
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('success', true);

        $media = Media::query()->firstOrFail();

        $this->assertStringContainsString('medias/videos/', (string) $media->media_url);
        $this->assertStringContainsString('medias/covers/', (string) $media->cover_url);
        Storage::disk('s3')->assertExists('medias/videos/'.basename((string) $media->media_url));
        Storage::disk('s3')->assertExists('medias/covers/'.basename((string) $media->cover_url));
        $this->assertSame('0.00', (string) $media->price);
        $this->assertDatabaseHas('category_media', ['category_id' => $categoryId, 'media_id' => $media->id]);
        $this->assertTrue(AdminNotification::query()->where('type', 'media_created')->where('to_user_id', $admin->id)->exists());
    }

    public function test_store_allows_nullable_media_and_cover_urls_without_uploads(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'username' => 'owner', 'password' => 'password']);

        $response = $this->postJson('/api/v1/media', [
            'media_title' => 'Draft',
            'type' => 'music',
            'user_id' => $owner->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $media = Media::query()->firstOrFail();

        $this->assertNull($media->media_url);
        $this->assertNull($media->cover_url);
        $this->assertSame('0.00', (string) $media->price);
    }

    public function test_store_validates_category_ids_before_syncing(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'username' => 'owner', 'password' => 'password']);

        $response = $this->postJson('/api/v1/media', [
            'media_title' => 'Invalid category',
            'type' => 'music',
            'category_ids' => [999],
            'user_id' => $owner->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids.0');
    }
}
