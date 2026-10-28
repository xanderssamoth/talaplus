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
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('surname')->nullable();
            $table->string('partner_name')->nullable();
            $table->text('about_me')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('p_o_box')->nullable();
            $table->string('currency')->default('USD');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('api_key')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('promo_code')->nullable()->unique();
            $table->dateTime('promo_code_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_email_confirmed_at')->nullable();
            $table->timestamp('two_factor_phone_confirmed_at')->nullable();
            $table->boolean('tips_at_every_login')->default(true);
            $table->boolean('is_online')->default(true);
            $table->boolean('christian_preference')->default(false);
            $table->foreignId('belongs_to')->nullable();
            $table->string('referral_code_used')->nullable();
            $table->foreignId('child_of')->nullable();
            $table->string('child_lock_code')->nullable();
            $table->string('status')->default('created');
            $table->string('type')->default('uncertified');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->integer('validity');
            $table->string('status')->default('expired');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->boolean('is_read')->default(false);
            $table->foreignId('to_user_id')->nullable();
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

    public function test_user_can_generate_refresh_and_terminate_own_referral_code(): void
    {
        $user = User::create(['email' => 'referrer@example.com', 'username' => 'referrer', 'password' => 'password']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/user/{$user->id}/become-referrer")
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertMatchesRegularExpression('/^[A-Z]{5}[0-9]{5}$/', $user->promo_code);
        $this->assertNotNull($user->promo_code_at);
        $firstCode = $user->promo_code;
        $this->assertDatabaseHas('promo_codes', [
            'code' => $firstCode,
            'user_id' => $user->id,
            'status' => 'active',
            'validity' => $user->promo_code_at->copy()->addMonthsNoOverflow(2)->getTimestamp() - $user->promo_code_at->getTimestamp(),
        ]);

        $user->promo_code_at = now()->subMonth();
        $user->save();

        $this->postJson("/api/v1/user/{$user->id}/become-referrer")->assertOk();
        $this->assertTrue($user->fresh()->promo_code_at->greaterThan(now()->subMinute()));
        $this->assertDatabaseHas('promo_codes', ['code' => $firstCode, 'status' => 'expired']);
        $this->assertDatabaseHas('promo_codes', ['code' => $user->fresh()->promo_code, 'status' => 'active']);

        $this->postJson("/api/v1/user/{$user->id}/terminate-referral")
            ->assertOk()
            ->assertJsonPath('data.promo_code', null);
        $this->assertNull($user->fresh()->promo_code);
        $this->assertDatabaseMissing('promo_codes', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_user_store_links_new_account_to_the_matching_referral_code(): void
    {
        $referrer = User::create([
            'email' => 'registration-referrer@example.com',
            'username' => 'registration-referrer',
            'password' => 'password',
            'promo_code' => 'ABCDE12345',
        ]);
        $otherUser = User::create(['email' => 'other-parent@example.com', 'username' => 'other-parent', 'password' => 'password']);
        Sanctum::actingAs($referrer);

        $this->postJson('/api/v1/user', [
            'username' => 'referred-account',
            'referral_code' => 'ABCDE12345',
            'belongs_to' => $otherUser->id,
        ])->assertOk()
            ->assertJsonPath('data.user.belongs_to', $referrer->id)
            ->assertJsonPath('data.user.referral_code_used', 'ABCDE12345');

        $this->assertDatabaseHas('users', [
            'username' => 'referred-account',
            'belongs_to' => $referrer->id,
            'referral_code_used' => 'ABCDE12345',
        ]);
    }

    public function test_user_store_and_update_manage_child_parent_relationship(): void
    {
        $parent = User::create(['username' => 'family-parent']);
        $administrator = User::create(['username' => 'family-admin']);
        Sanctum::actingAs($administrator);

        $this->postJson('/api/v1/user', [
            'username' => 'family-child',
            'child_of' => $parent->id,
        ])->assertUnprocessable()->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['username' => 'family-child']);

        $child = User::create(['username' => 'family-child', 'child_of' => $parent->id]);

        Sanctum::actingAs($child);
        $this->putJson("/api/v1/user/{$child->id}", ['child_of' => $child->id])
            ->assertUnprocessable();
        $this->putJson("/api/v1/user/{$child->id}", ['child_of' => null])
            ->assertUnprocessable();
        $this->assertSame($parent->id, $child->fresh()->child_of);
    }

    public function test_user_store_rejects_unknown_referral_code_in_both_languages(): void
    {
        $administrator = User::create(['email' => 'registration-admin@example.com', 'username' => 'registration-admin', 'password' => 'password']);
        Sanctum::actingAs($administrator);

        foreach (['fr', 'en'] as $locale) {
            $this->postJson("/api/v1/user?lang={$locale}", [
                'username' => "unknown-referrer-{$locale}",
                'referral_code' => 'UNKNOWN123',
            ])->assertNotFound()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', __('api.referral.referrer_not_found'))
                ->assertJsonMissingPath('data');
        }

        $this->assertDatabaseMissing('users', ['username' => 'unknown-referrer-fr']);
        $this->assertDatabaseMissing('users', ['username' => 'unknown-referrer-en']);
    }

    public function test_child_cannot_change_parental_fields_or_take_over_parent_account(): void
    {
        $parent = User::create(['username' => 'locked-parent', 'password' => 'parent-password', 'child_lock_code' => '1234']);
        $child = User::create(['username' => 'locked-child', 'child_of' => $parent->id]);
        Sanctum::actingAs($child);

        $this->putJson("/api/v1/user/{$child->id}", ['child_of' => null])->assertUnprocessable();
        $this->putJson("/api/v1/user/{$child->id}", ['child_lock_code' => null])->assertUnprocessable();
        $this->putJson("/api/v1/user/{$parent->id}", [
            'password' => 'new-parent-password',
            'password_confirmation' => 'new-parent-password',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/user/{$parent->id}")->assertForbidden();
        $this->getJson("/api/v1/user/{$parent->id}")->assertOk()->assertJsonMissingPath('data.child_lock_code');

        $this->assertSame($parent->id, $child->fresh()->child_of);
        $this->assertSame('1234', $parent->fresh()->child_lock_code);
        $this->assertNull($parent->fresh()->deleted_at);
        $this->assertFalse(Hash::check('new-parent-password', $parent->fresh()->password));
    }

    public function test_locked_child_cannot_use_another_users_media_lists_or_watchlist_actions(): void
    {
        $parent = User::create(['username' => 'viewer-parent', 'child_lock_code' => '1234']);
        $child = User::create(['username' => 'viewer-child', 'child_of' => $parent->id]);
        $owner = User::create(['username' => 'media-owner']);
        $adult = Media::create(['media_title' => ['fr' => 'Adultes'], 'user_id' => $owner->id]);
        $owner->watchlist()->attach($adult->id);
        Sanctum::actingAs($child);

        $this->getJson("/api/v1/user/{$owner->id}/medias")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/user/{$owner->id}/watchlist")->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/user/{$child->id}/watchlist/{$adult->id}")->assertNotFound();
        $this->postJson("/api/v1/user/{$owner->id}/watchlist/{$adult->id}")->assertForbidden();
    }

    public function test_generated_referral_codes_are_unique_for_different_users(): void
    {
        $first = User::create(['email' => 'unique-first@example.com', 'username' => 'unique-first', 'password' => 'password']);
        $second = User::create(['email' => 'unique-second@example.com', 'username' => 'unique-second', 'password' => 'password']);

        Sanctum::actingAs($first);
        $this->postJson("/api/v1/user/{$first->id}/become-referrer")->assertOk();
        Sanctum::actingAs($second);
        $this->postJson("/api/v1/user/{$second->id}/become-referrer")->assertOk();

        $this->assertNotSame($first->fresh()->promo_code, $second->fresh()->promo_code);
        $this->assertMatchesRegularExpression('/^[A-Z]{5}[0-9]{5}$/', $second->fresh()->promo_code);
    }

    public function test_user_update_rejects_another_users_referral_code(): void
    {
        $referrer = User::create(['email' => 'code-owner@example.com', 'username' => 'code-owner', 'promo_code' => 'ABCDE12345']);
        $user = User::create(['email' => 'code-copy@example.com', 'username' => 'code-copy', 'password' => 'password']);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/user/{$user->id}", ['promo_code' => $referrer->promo_code])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertNull($user->fresh()->promo_code);
    }

    public function test_user_referrees_only_lists_users_whose_belongs_to_matches_the_requested_user(): void
    {
        $referrer = User::create(['email' => 'referrer-list@example.com', 'username' => 'referrer-list', 'password' => 'password', 'promo_code' => 'ABCDE12345']);
        $otherReferrer = User::create(['email' => 'other-referrer@example.com', 'username' => 'other-referrer', 'password' => 'password', 'promo_code' => 'FGHIJ67890']);
        $firstReferree = User::create(['email' => 'first-referree@example.com', 'username' => 'first-referree', 'belongs_to' => $referrer->id, 'referral_code_used' => 'ABCDE12345']);
        $secondReferree = User::create(['email' => 'second-referree@example.com', 'username' => 'second-referree', 'belongs_to' => $referrer->id, 'referral_code_used' => 'ABCDE12345']);
        User::create(['email' => 'unrelated-referree@example.com', 'username' => 'unrelated-referree', 'belongs_to' => $otherReferrer->id, 'referral_code_used' => 'FGHIJ67890']);
        Sanctum::actingAs($otherReferrer);

        $this->getJson("/api/v1/user/{$referrer->id}/referrees")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2)
            ->assertJsonPath('lastPage', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $secondReferree->id)
            ->assertJsonPath('data.1.id', $firstReferree->id);
    }

    public function test_user_referrees_returns_empty_list_or_not_found_as_appropriate(): void
    {
        $referrer = User::create(['email' => 'empty-referrer@example.com', 'username' => 'empty-referrer', 'password' => 'password']);
        Sanctum::actingAs($referrer);

        $this->getJson("/api/v1/user/{$referrer->id}/referrees")
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/user/999999/referrees')->assertNotFound();
    }

    public function test_expired_or_replaced_referral_code_hides_old_referral_links(): void
    {
        $referrer = User::create(['username' => 'former-referrer', 'promo_code' => 'ABCDE12345']);
        $referree = User::create(['username' => 'former-referree', 'belongs_to' => $referrer->id, 'referral_code_used' => 'ABCDE12345']);
        Sanctum::actingAs($referree);

        $this->getJson("/api/v1/user/{$referree->id}/referrer")
            ->assertOk()
            ->assertJsonPath('data.id', $referrer->id);

        $referrer->update(['promo_code' => null]);

        $this->getJson("/api/v1/user/{$referree->id}/referrer")
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->getJson("/api/v1/user/{$referrer->id}/referrees")
            ->assertOk()
            ->assertJsonPath('count', 0);

        $referrer->update(['promo_code' => 'FGHIJ67890']);
        $this->getJson("/api/v1/user/{$referrer->id}/referrees")
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->assertSame('ABCDE12345', $referree->fresh()->referral_code_used);
    }

    public function test_user_referrees_are_paginated(): void
    {
        $referrer = User::create(['email' => 'paginated-referrer@example.com', 'username' => 'paginated-referrer', 'password' => 'password', 'promo_code' => 'ABCDE12345']);

        for ($index = 1; $index <= 11; $index++) {
            User::create([
                'email' => "referree-{$index}@example.com",
                'username' => "referree-{$index}",
                'belongs_to' => $referrer->id,
                'referral_code_used' => 'ABCDE12345',
            ]);
        }

        Sanctum::actingAs($referrer);

        $this->getJson("/api/v1/user/{$referrer->id}/referrees?page=2")
            ->assertOk()
            ->assertJsonPath('count', 11)
            ->assertJsonPath('lastPage', 2)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_manage_another_users_referral_code(): void
    {
        $user = User::create(['email' => 'referrer-one@example.com', 'username' => 'referrer-one', 'password' => 'password']);
        $otherUser = User::create(['email' => 'referrer-two@example.com', 'username' => 'referrer-two', 'password' => 'password']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/user/{$otherUser->id}/become-referrer")->assertForbidden();
        $this->postJson("/api/v1/user/{$otherUser->id}/terminate-referral")->assertForbidden();
        $this->assertNull($otherUser->fresh()->promo_code);
    }

    public function test_referral_actions_require_authentication(): void
    {
        $user = User::create(['email' => 'guest-target@example.com', 'username' => 'guest-target', 'password' => 'password']);

        $this->postJson("/api/v1/user/{$user->id}/become-referrer")->assertUnauthorized();
        $this->postJson("/api/v1/user/{$user->id}/terminate-referral")->assertUnauthorized();
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

    public function test_user_store_saves_optional_base64_avatar_and_cover_without_accepting_server_fields(): void
    {
        Storage::fake('s3');
        $administrator = User::create(['email' => 'administrator@example.com', 'username' => 'administrator', 'password' => 'password']);
        $imageBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        Sanctum::actingAs($administrator);

        $this->postJson('/api/v1/user', [
            'email' => 'new-user@example.com',
            'username' => 'new-user',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'avatar_base64' => $imageBase64,
            'cover_base64' => $imageBase64,
            'status' => 'blocked',
            'type' => 'certified',
            'api_token' => 'must-not-be-stored',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.status', 'activated')
            ->assertJsonPath('data.user.type', 'uncertified');

        $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

        $this->assertStringContainsString('users/avatars/', (string) $user->avatar_url);
        $this->assertStringContainsString('users/covers/', (string) $user->cover_url);
        Storage::disk('s3')->assertExists('users/avatars/'.basename((string) $user->avatar_url));
        Storage::disk('s3')->assertExists('users/covers/'.basename((string) $user->cover_url));
        $this->assertDatabaseCount('files', 0);
    }

    public function test_child_lock_code_toggles_only_for_the_authenticated_parent(): void
    {
        $parent = User::create(['username' => 'parent']);
        $other = User::create(['username' => 'other']);

        Sanctum::actingAs($parent);

        $this->patchJson("/api/v1/user/{$parent->id}/child-lock-code")
            ->assertOk()
            ->assertJsonPath('data.child_lock_code', $parent->fresh()->child_lock_code);
        $this->assertNotEmpty($parent->fresh()->child_lock_code);

        $this->patchJson("/api/v1/user/{$other->id}/child-lock-code")
            ->assertForbidden();
        $this->assertNull($other->fresh()->child_lock_code);

        $this->patchJson("/api/v1/user/{$parent->id}/child-lock-code")
            ->assertOk();
        $this->assertNull($parent->fresh()->child_lock_code);
    }

    public function test_user_update_saves_optional_base64_images_and_supported_account_fields(): void
    {
        Storage::fake('s3');
        $user = User::create([
            'email' => 'profile@example.com',
            'username' => 'profile-user',
            'password' => 'password',
            'status' => 'blocked',
            'type' => 'certified',
        ]);
        $imageBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/user/{$user->id}", [
            'avatar_base64' => $imageBase64,
            'cover_base64' => $imageBase64,
            'api_token' => 'must-not-be-stored',
            'status' => 'activated',
            'type' => 'uncertified',
            'partner_name' => 'Partenaire',
            'currency' => 'CDF',
            'api_key' => 'api-key',
            'promo_code' => 'PROMO-123',
            'two_factor_secret' => 'two-factor-secret',
            'two_factor_recovery_codes' => 'recovery-codes',
            'two_factor_email_confirmed_at' => '2026-09-24 12:00:00',
            'two_factor_phone_confirmed_at' => '2026-09-24 12:00:00',
            'tips_at_every_login' => false,
            'is_online' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'activated')
            ->assertJsonPath('data.type', 'uncertified');

        $user->refresh();

        $this->assertStringContainsString('users/avatars/', (string) $user->avatar_url);
        $this->assertStringContainsString('users/covers/', (string) $user->cover_url);
        Storage::disk('s3')->assertExists('users/avatars/'.basename((string) $user->avatar_url));
        Storage::disk('s3')->assertExists('users/covers/'.basename((string) $user->cover_url));
        $this->assertSame('Partenaire', $user->partner_name);
        $this->assertSame('CDF', $user->currency);
        $this->assertNull($user->child_lock_code);
        $this->assertSame('api-key', $user->api_key);
        $this->assertSame('PROMO-123', $user->promo_code);
        $this->assertSame('two-factor-secret', $user->two_factor_secret);
        $this->assertSame('recovery-codes', $user->two_factor_recovery_codes);
        $this->assertFalse($user->tips_at_every_login);
        $this->assertFalse($user->is_online);
        $this->assertDatabaseCount('files', 0);
    }

    public function test_user_store_assigns_created_status_when_no_password_is_provided(): void
    {
        $administrator = User::create(['email' => 'administrator-created@example.com', 'username' => 'administrator-created', 'password' => 'password']);

        Sanctum::actingAs($administrator);

        $this->postJson('/api/v1/user', ['username' => 'pending-user'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.status', 'created')
            ->assertJsonPath('data.user.type', 'uncertified')
            ->assertJsonPath('data.user.currency', 'USD');
    }
}
