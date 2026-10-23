<?php

namespace Tests\Feature\Api;

use App\Models\ChildInvitation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChildInvitationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('child_invitations');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->foreignId('child_of')->nullable();
            $table->string('child_lock_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('child_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users');
            $table->foreignId('child_user_id')->constrained('users');
            $table->string('status');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['parent_user_id', 'child_user_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_parent_invites_multiple_children_and_each_child_must_accept(): void
    {
        $parent = User::create(['username' => 'parent']);
        $firstChild = User::create(['username' => 'first-child']);
        $secondChild = User::create(['username' => 'second-child']);

        Sanctum::actingAs($parent);
        $this->postJson('/api/v1/user/children/invitations', [
            'child_ids' => [$firstChild->id, $secondChild->id],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->assertNull($firstChild->fresh()->child_of);
        $this->assertNull($secondChild->fresh()->child_of);

        $invitation = ChildInvitation::query()->where('child_user_id', $firstChild->id)->firstOrFail();

        Sanctum::actingAs($firstChild);
        $this->getJson('/api/v1/user/children/invitations')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame($parent->id, $firstChild->fresh()->child_of);
        $this->assertNull($secondChild->fresh()->child_of);
        $this->assertSame($firstChild->id, $parent->children()->firstOrFail()->id);
        $this->getJson('/api/v1/user/children/invitations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_the_invited_child_can_accept_and_cannot_accept_twice(): void
    {
        $parent = User::create(['username' => 'parent']);
        $child = User::create(['username' => 'child']);
        $stranger = User::create(['username' => 'stranger']);
        $invitation = ChildInvitation::factory()->create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        Sanctum::actingAs($child);
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/accept")->assertOk();
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_expired_invitation_cannot_be_accepted_and_decline_does_not_link_account(): void
    {
        $parent = User::create(['username' => 'parent']);
        $child = User::create(['username' => 'child']);
        $invitation = ChildInvitation::factory()->create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($child);
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/accept")
            ->assertStatus(409);

        $invitation->update(['expires_at' => now()->addDay()]);
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertNull($child->fresh()->child_of);
    }

    public function test_invitation_without_expiration_is_reissued_before_acceptance(): void
    {
        $parent = User::create(['username' => 'parent']);
        $child = User::create(['username' => 'child']);
        $invitation = ChildInvitation::factory()->create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'expires_at' => null,
        ]);

        Sanctum::actingAs($child);
        $this->postJson("/api/v1/user/children/invitations/{$invitation->id}/accept")
            ->assertStatus(409);

        Sanctum::actingAs($parent);
        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [$child->id]])
            ->assertOk();

        $this->assertTrue($invitation->fresh()->expires_at->isFuture());
    }

    public function test_bulk_invitation_is_atomic_and_rejects_invalid_relationships(): void
    {
        $parent = User::create(['username' => 'parent']);
        $child = User::create(['username' => 'child']);
        $linked = User::create(['username' => 'linked', 'child_of' => $parent->id]);

        Sanctum::actingAs($parent);
        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [$child->id, $linked->id]])
            ->assertStatus(409);
        $this->assertDatabaseCount('child_invitations', 0);

        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [$parent->id]])
            ->assertStatus(409);
        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [$child->id, $child->id]])
            ->assertUnprocessable();
        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [999]])
            ->assertStatus(409);

        Sanctum::actingAs($linked);
        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [$child->id]])
            ->assertStatus(409);
    }

    public function test_invitation_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/user/children/invitations', ['child_ids' => [1]])->assertUnauthorized();
    }

    public function test_invitation_migration_uses_named_indexes_from_sql_schema(): void
    {
        Schema::dropIfExists('child_invitations');

        $migration = require database_path('migrations/2026_10_23_065236_create_child_invitations_table.php');
        $migration->up();

        $indexes = collect(Schema::getIndexes('child_invitations'))->pluck('name');

        $this->assertContains('fk_childinvitations_parentusers_idx', $indexes);
        $this->assertContains('fk_childinvitations_childusers_idx', $indexes);
    }

    public function test_invitation_errors_are_translated_and_do_not_expose_data(): void
    {
        $parent = User::create(['username' => 'parent']);
        Sanctum::actingAs($parent);

        foreach (['fr', 'en'] as $locale) {
            $this->postJson("/api/v1/user/children/invitations?lang={$locale}", [
                'child_ids' => [$parent->id],
            ])->assertStatus(409)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', __('api.child_invitation.self_invitation'))
                ->assertJsonMissingPath('data');
        }
    }
}
