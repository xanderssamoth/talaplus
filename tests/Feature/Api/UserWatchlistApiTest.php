<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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
            $table->text('avatar_url')->nullable();
            $table->string('status')->default('created');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_resets', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('token')->nullable();
            $table->text('former_password')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->json('role_name')->nullable();
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

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/user/{$user->id}/watchlist/{$media->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('media_user', [
            'user_id' => $user->id,
            'media_id' => $media->id,
        ]);

        $this->getJson("/api/v1/user/{$user->id}/watchlist")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $media->id);

        $this->deleteJson("/api/v1/user/{$user->id}/watchlist/{$media->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('media_user', [
            'user_id' => $user->id,
            'media_id' => $media->id,
        ]);
    }

    public function test_authenticated_user_can_update_a_status_with_the_status_request_field(): void
    {
        $user = User::create(['email' => 'status@example.com', 'username' => 'status-user', 'password' => 'password']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/status", ['status' => 'activated'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'activated');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'activated']);
    }

    public function test_validation_errors_include_the_api_failure_flag(): void
    {
        $user = User::create(['email' => 'password@example.com', 'username' => 'password-user', 'password' => 'password']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/password", [
            'former_password' => 'password',
            'new_password' => 'new-password',
            'password_confirmation' => 'does-not-match',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['password_confirmation'],
            ]);
    }

    public function test_update_password_does_not_accept_the_legacy_confirmation_field(): void
    {
        $user = User::create(['email' => 'legacy-password@example.com', 'username' => 'legacy-password-user', 'password' => 'password']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/password", [
            'former_password' => 'password',
            'new_password' => 'new-password',
            'confirm_passord' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['password_confirmation']]);
    }

    public function test_invalid_former_password_does_not_include_user_data_in_the_error_response(): void
    {
        $user = User::create(['email' => 'former-password@example.com', 'username' => 'former-password-user', 'password' => 'password']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/password", [
            'former_password' => 'incorrect-password',
            'new_password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.auth.former_password_invalid'))
            ->assertJsonMissingPath('data');
    }

    public function test_updated_password_is_saved_as_a_hash_in_the_password_reset_record(): void
    {
        $user = User::create(['email' => 'password-history@example.com', 'username' => 'password-history-user', 'password' => 'password']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/password", [
            'former_password' => 'password',
            'new_password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $passwordReset = PasswordReset::query()->where('email', $user->email)->firstOrFail();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
        $this->assertNotSame('new-password', $passwordReset->former_password);
        $this->assertTrue(Hash::check('new-password', $passwordReset->former_password));
    }

    public function test_current_password_cannot_be_reused_as_a_new_password(): void
    {
        $user = User::create(['email' => 'same-password@example.com', 'username' => 'same-password-user', 'password' => 'password']);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/password", [
            'former_password' => 'password',
            'new_password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.auth.password_unchanged'))
            ->assertJsonMissingPath('data');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_role_update_preserves_existing_roles_and_selects_only_the_requested_role(): void
    {
        $user = User::create(['email' => 'roles@example.com', 'username' => 'roles-user', 'password' => 'password']);
        $formerRole = Role::create(['role_name' => ['fr' => 'Ancien']]);
        $existingRole = Role::create(['role_name' => ['fr' => 'Existant']]);
        $newRole = Role::create(['role_name' => ['fr' => 'Nouveau']]);

        $user->roles()->attach([
            $formerRole->id => ['is_selected' => true],
            $existingRole->id => ['is_selected' => false],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/role", ['role_id' => $existingRole->id])->assertOk();

        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $formerRole->id, 'is_selected' => false]);
        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $existingRole->id, 'is_selected' => true]);
        $this->assertDatabaseCount('role_user', 2);

        $this->patchJson("/api/v1/user/{$user->id}/role", ['role_id' => $newRole->id])->assertOk();

        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $existingRole->id, 'is_selected' => false]);
        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $newRole->id, 'is_selected' => true]);
        $this->assertDatabaseCount('role_user', 3);
    }

    public function test_avatar_update_stores_a_base64_image_on_s3_without_creating_a_file_record(): void
    {
        Storage::fake('s3');
        $user = User::create(['email' => 'avatar@example.com', 'username' => 'avatar-user', 'password' => 'password']);
        $avatarBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/user/{$user->id}/avatar", ['avatar_base64' => $avatarBase64])
            ->assertOk()
            ->assertJsonPath('success', true);

        $avatarUrl = (string) $user->refresh()->avatar_url;

        $this->assertStringContainsString('users/avatars/', $avatarUrl);
        Storage::disk('s3')->assertExists('users/avatars/'.basename($avatarUrl));
        $this->assertDatabaseCount('files', 0);
    }
}
