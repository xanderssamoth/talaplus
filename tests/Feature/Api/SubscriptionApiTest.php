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
}
