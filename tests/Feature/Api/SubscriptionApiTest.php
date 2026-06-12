<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->text('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('follower_id');
            $table->boolean('granted')->default(false);
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
    }

    public function test_store_creates_new_follower_notification(): void
    {
        $followed = User::create(['email' => 'followed@example.com', 'password' => 'password']);
        $follower = User::create(['email' => 'follower@example.com', 'password' => 'password']);

        $response = $this->postJson('/api/v1/subscription', [
            'user_id' => $followed->id,
            'follower_id' => $follower->id,
            'granted' => true,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'new_follower')
            ->where('from_user_id', $follower->id)
            ->where('to_user_id', $followed->id)
            ->exists());
    }

    public function test_is_follower_checks_existing_subscription_between_users(): void
    {
        $followed = User::create(['email' => 'followed@example.com', 'password' => 'password']);
        $follower = User::create(['email' => 'follower@example.com', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password']);
        $subscription = Subscription::create([
            'user_id' => $followed->id,
            'follower_id' => $follower->id,
            'granted' => true,
        ]);

        $this->getJson("/api/v1/subscription/is-follower?user_id={$followed->id}&follower_id={$follower->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_follower', true)
            ->assertJsonPath('data.subscription.id', $subscription->id);

        $this->getJson("/api/v1/subscription/is-follower?user_id={$followed->id}&follower_id={$otherUser->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_follower', false)
            ->assertJsonPath('data.subscription', null);
    }

    public function test_unfollow_deletes_subscription_and_new_follower_notification(): void
    {
        $followed = User::create(['email' => 'followed@example.com', 'password' => 'password']);
        $follower = User::create(['email' => 'follower@example.com', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password']);
        Subscription::create([
            'user_id' => $followed->id,
            'follower_id' => $follower->id,
            'granted' => true,
        ]);
        Subscription::create([
            'user_id' => $followed->id,
            'follower_id' => $otherUser->id,
            'granted' => true,
        ]);
        AdminNotification::create([
            'type' => 'new_follower',
            'from_user_id' => $follower->id,
            'to_user_id' => $followed->id,
        ]);
        $otherNotification = AdminNotification::create([
            'type' => 'new_follower',
            'from_user_id' => $otherUser->id,
            'to_user_id' => $followed->id,
        ]);

        $this->deleteJson('/api/v1/subscription/unfollow', [
            'user_id' => $followed->id,
            'follower_id' => $follower->id,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->assertFalse(Subscription::query()
            ->where('user_id', $followed->id)
            ->where('follower_id', $follower->id)
            ->exists());
        $this->assertFalse(AdminNotification::query()
            ->where('type', 'new_follower')
            ->where('from_user_id', $follower->id)
            ->where('to_user_id', $followed->id)
            ->exists());
        $this->assertTrue(Subscription::query()
            ->where('user_id', $followed->id)
            ->where('follower_id', $otherUser->id)
            ->exists());
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);
    }

    public function test_user_subscription_lists_return_subscriptions_followers_and_connections(): void
    {
        $currentUser = User::create(['email' => 'current@example.com', 'password' => 'password']);
        $followedUser = User::create(['email' => 'followed@example.com', 'password' => 'password']);
        $followerUser = User::create(['email' => 'follower@example.com', 'password' => 'password']);
        $unrelatedUser = User::create(['email' => 'unrelated@example.com', 'password' => 'password']);

        $currentFollows = Subscription::create([
            'user_id' => $followedUser->id,
            'follower_id' => $currentUser->id,
            'granted' => true,
        ]);
        $currentIsFollowed = Subscription::create([
            'user_id' => $currentUser->id,
            'follower_id' => $followerUser->id,
            'granted' => true,
        ]);
        Subscription::create([
            'user_id' => $unrelatedUser->id,
            'follower_id' => $followedUser->id,
            'granted' => true,
        ]);

        $this->getJson("/api/v1/subscription/user/{$currentUser->id}/subscriptions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentFollows->id)
            ->assertJsonPath('data.0.user.id', $followedUser->id)
            ->assertJsonPath('data.0.follower.id', $currentUser->id)
            ->assertJsonPath('count', 1);

        $this->getJson("/api/v1/subscription/user/{$currentUser->id}/followers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentIsFollowed->id)
            ->assertJsonPath('data.0.user.id', $currentUser->id)
            ->assertJsonPath('data.0.follower.id', $followerUser->id)
            ->assertJsonPath('count', 1);

        $connections = $this->getJson("/api/v1/subscription/user/{$currentUser->id}/connections")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('count', 2);

        $connectionIds = collect($connections->json('data'))->pluck('id')->all();
        $this->assertContains($currentFollows->id, $connectionIds);
        $this->assertContains($currentIsFollowed->id, $connectionIds);
    }
}
