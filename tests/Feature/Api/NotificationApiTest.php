<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationApiTest extends TestCase
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

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->foreignId('from_user_id')->nullable();
            $table->foreignId('to_user_id')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('comment_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_user_notifications_returns_notifications_received_by_user(): void
    {
        [$sender, $recipient, $otherUser] = $this->users();
        $visible = AdminNotification::create(['type' => 'visible', 'from_user_id' => $sender->id, 'to_user_id' => $recipient->id]);
        AdminNotification::create(['type' => 'hidden', 'from_user_id' => $sender->id, 'to_user_id' => $otherUser->id]);

        $response = $this->getJson("/api/v1/notification/user/{$recipient->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('count', 1);
    }

    public function test_mark_as_read_marks_one_notification_as_read(): void
    {
        [$sender, $recipient] = $this->users();
        $notification = AdminNotification::create(['type' => 'single', 'from_user_id' => $sender->id, 'to_user_id' => $recipient->id, 'is_read' => false]);

        $response = $this->patchJson("/api/v1/notification/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_mark_all_as_read_marks_all_notifications_received_by_user(): void
    {
        [$sender, $recipient, $otherUser] = $this->users();
        $first = AdminNotification::create(['type' => 'first', 'from_user_id' => $sender->id, 'to_user_id' => $recipient->id, 'is_read' => false]);
        $second = AdminNotification::create(['type' => 'second', 'from_user_id' => $sender->id, 'to_user_id' => $recipient->id, 'is_read' => false]);
        $other = AdminNotification::create(['type' => 'other', 'from_user_id' => $sender->id, 'to_user_id' => $otherUser->id, 'is_read' => false]);

        $response = $this->patchJson("/api/v1/notification/user/{$recipient->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('notifications', ['id' => $first->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $second->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $other->id, 'is_read' => false]);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function users(): array
    {
        return [
            User::create(['email' => 'sender@example.com', 'username' => 'sender', 'password' => 'password']),
            User::create(['email' => 'recipient@example.com', 'username' => 'recipient', 'password' => 'password']),
            User::create(['email' => 'other@example.com', 'username' => 'other', 'password' => 'password']),
        ];
    }
}
