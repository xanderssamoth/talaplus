<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupApiTest extends TestCase
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

        foreach (['group_user', 'groups', 'users'] as $table) {
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

        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->text('group_name');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('group_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id');
            $table->foreignId('user_id');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_group_store_adds_its_owner_as_an_administrator(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);

        $this->postJson('/api/v1/group', [
            'group_name' => 'Prayer group',
            'user_id' => $owner->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.group_name', 'Prayer group')
            ->assertJsonPath('data.user_id', $owner->id);

        $group = Group::query()->firstOrFail();

        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'is_admin' => true,
        ]);
    }

    public function test_user_groups_lists_owned_and_joined_groups_without_duplicates(): void
    {
        $user = User::create(['email' => 'member@example.com', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password']);
        $ownedGroup = Group::create(['group_name' => 'Owned group', 'user_id' => $user->id]);
        $joinedGroup = Group::create(['group_name' => 'Joined group', 'user_id' => $otherUser->id]);
        Group::create(['group_name' => 'Unrelated group', 'user_id' => $otherUser->id]);

        $ownedGroup->users()->attach($user->id, ['is_admin' => true]);
        $joinedGroup->users()->attach($user->id);

        $this->getJson("/api/v1/group/user/{$user->id}")
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $ownedGroup->id])
            ->assertJsonFragment(['id' => $joinedGroup->id]);
    }

    public function test_group_store_without_user_does_not_create_a_pivot_record(): void
    {
        $this->postJson('/api/v1/group', ['group_name' => 'Ownerless group'])
            ->assertOk()
            ->assertJsonPath('data.user_id', null);

        $this->assertDatabaseCount('group_user', 0);
    }
}
