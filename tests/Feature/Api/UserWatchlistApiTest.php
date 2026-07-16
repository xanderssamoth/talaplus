<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserWatchlistApiTest extends TestCase
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
            'bank_cards',
            'blocked_users',
            'notifications',
            'reports',
            'money_transfers',
            'payments',
            'promo_codes',
            'customer_orders',
            'carts',
            'messages',
            'hashtag_comment',
            'hashtag_media',
            'comments',
            'hashtags',
            'subscriptions',
            'group_user',
            'groups',
            'specifications',
            'role_user',
            'roles',
            'products',
            'category_media',
            'categories',
            'pricings',
            'pricing_descriptions',
            'reasons',
            'password_resets',
            'personal_access_tokens',
            'sessions',
            'media_user',
            'media_progresses',
            'histories',
            'reactions',
            'files',
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->json('media_title')->nullable();
            $table->longText('media_description')->nullable();
            $table->text('media_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('for_youth')->default(false);
            $table->string('type')->default('film_series');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->string('file_type')->default('photo');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('user_id')->nullable();
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

        Schema::create('histories', function (Blueprint $table): void {
            $table->id();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_progresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id');
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_media_can_be_removed_from_user_watchlist(): void
    {
        $user = User::create(['email' => 'viewer@example.com', 'username' => 'viewer', 'password' => 'password']);
        $media = Media::create([
            'media_title' => ['fr' => 'Film test', 'en' => 'Test movie'],
            'user_id' => $user->id,
        ]);

        $this->postJson("/api/v1/user/{$user->id}/watchlist/{$media->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('media_user', [
            'user_id' => $user->id,
            'media_id' => $media->id,
        ]);

        $this->deleteJson("/api/v1/user/{$user->id}/watchlist/{$media->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('media_user', [
            'user_id' => $user->id,
            'media_id' => $media->id,
        ]);
    }
}
