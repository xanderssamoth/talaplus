<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\History;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('histories');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('username')->nullable()->unique();
            $table->text('password')->nullable();
            $table->text('api_token')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->boolean('christian_preference')->default(false);
            $table->string('status')->default('created');
            $table->string('type')->default('uncertified');
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

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
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
    }

    public function test_store_creates_user_password_reset_notification_and_api_token(): void
    {
        $role = Role::create(['role_name' => ['fr' => 'Membre', 'en' => 'Member']]);

        $response = $this->postJson('/api/v1/user', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+243999000111',
            'username' => 'johndoe',
            'password' => 'secret-password',
            'confirm_password' => 'secret-password',
            'role_id' => $role->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'password_reset']]);

        $user = User::query()->where('email', 'john@example.com')->firstOrFail();

        $this->assertNotNull($user->api_token);
        $this->assertSame(6, strlen(PasswordReset::query()->where('email', 'john@example.com')->firstOrFail()->token));
        $this->assertTrue(AdminNotification::query()->where('type', 'welcome_new_user')->where('to_user_id', $user->id)->exists());
        $this->assertTrue($user->roles()->where('roles.id', $role->id)->wherePivot('is_selected', true)->exists());
    }

    public function test_login_requires_email_verification_and_returns_user_in_error(): void
    {
        User::create([
            'email' => 'not-verified@example.com',
            'username' => 'notverified',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/user/login', [
            'username' => 'not-verified@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.email', 'not-verified@example.com');
    }

    public function test_login_updates_api_token_when_credentials_are_valid(): void
    {
        User::create([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
            'username' => 'verified',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/user/login', [
            'username' => 'verified@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertNotNull(User::query()->where('email', 'verified@example.com')->firstOrFail()->api_token);
    }

    public function test_search_records_history(): void
    {
        $searcher = User::create(['email' => 'searcher@example.com', 'password' => 'password']);
        User::create(['firstname' => 'Grace', 'email' => 'grace@example.com', 'password' => 'password']);

        $response = $this->getJson('/api/v1/user/search/by-word?word=Gra&user_id='.$searcher->id);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(History::query()
            ->where('entity', 'user')
            ->where('action', 'search')
            ->where('user_id', $searcher->id)
            ->where('word', 'Gra')
            ->exists());
    }
}
