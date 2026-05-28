<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\File;
use App\Models\History;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('histories');
        Schema::dropIfExists('files');
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

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->longText('file_description')->nullable();
            $table->string('file_type')->default('photo');
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
        $role = Role::create(['role_name' => ['fr' => 'Membre', 'en' => 'Member', 'ln' => 'Mosangani']]);

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

    public function test_store_creates_member_role_when_missing_and_allows_username_only_without_password_reset(): void
    {
        $response = $this->postJson('/api/v1/user', [
            'username' => 'anonymous',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.password_reset', null);

        $user = User::query()->where('username', 'anonymous')->firstOrFail();
        $role = Role::query()->where('role_name->fr', 'Membre')->firstOrFail();

        $this->assertNull($user->password);
        $this->assertSame('Member', $role->getTranslation('role_name', 'en'));
        $this->assertSame('Mosangani', $role->getTranslation('role_name', 'ln'));
        $this->assertSame(0, PasswordReset::query()->count());
        $this->assertTrue($user->roles()->where('roles.id', $role->id)->wherePivot('is_selected', true)->exists());
    }

    public function test_store_generates_password_and_password_reset_when_contact_exists_without_password(): void
    {
        $response = $this->postJson('/api/v1/user', [
            'email' => 'generated@example.com',
            'phone' => '+243999111222',
            'username' => 'generated',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user = User::query()->where('email', 'generated@example.com')->firstOrFail();
        $passwordReset = PasswordReset::query()->where('email', 'generated@example.com')->firstOrFail();

        $this->assertNotNull($user->password);
        $this->assertNotNull($passwordReset->former_password);
        $this->assertSame(8, strlen($passwordReset->former_password));
        $this->assertTrue(Hash::check($passwordReset->former_password, $user->password));
        $this->assertSame('+243999111222', $passwordReset->phone);
        $this->assertSame(6, strlen($passwordReset->token));
    }

    public function test_store_requires_password_confirmation_when_password_is_present(): void
    {
        $response = $this->postJson('/api/v1/user', [
            'username' => 'missing-confirmation',
            'password' => 'secret-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password_confirmation');
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

    public function test_update_requires_password_confirmation_when_password_is_changed(): void
    {
        $user = User::create(['email' => 'update@example.com', 'username' => 'update', 'password' => 'password']);

        $this->patchJson("/api/v1/user/{$user->id}", [
            'password' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password_confirmation');

        $this->patchJson("/api/v1/user/{$user->id}", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_update_password_accepts_password_confirmation(): void
    {
        $user = User::create(['email' => 'password@example.com', 'username' => 'passworduser', 'password' => 'old-password']);

        $response = $this->patchJson("/api/v1/user/{$user->id}/password", [
            'former_password' => 'old-password',
            'new_password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_user_avatar_and_files_are_saved_to_s3(): void
    {
        Storage::fake('s3');
        $user = User::create(['email' => 'files@example.com', 'username' => 'files', 'password' => 'password']);

        $avatarResponse = $this->patch('/api/v1/user/'.$user->id.'/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json']);

        $avatarResponse->assertOk();
        $user->refresh();
        Storage::disk('s3')->assertExists('users/avatars/'.basename((string) $user->avatar_url));

        $this->post('/api/v1/user/'.$user->id.'/file', [
            'files' => [
                UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
            ],
            'file_type' => 'document',
        ], ['Accept' => 'application/json'])->assertOk();

        $file = File::query()->where('file_name', 'identity.pdf')->firstOrFail();
        $this->assertSame($user->id, $file->user_id);
        Storage::disk('s3')->assertExists('users/files/'.basename((string) $file->file_url));
    }
}
