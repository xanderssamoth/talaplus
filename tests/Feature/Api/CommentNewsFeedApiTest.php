<?php

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommentNewsFeedApiTest extends TestCase
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
            'files',
            'reactions',
            'comments',
            'subscriptions',
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

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->longText('comment_content')->nullable();
            $table->unsignedBigInteger('answered_for')->nullable();
            $table->string('type')->default('post');
            $table->string('for_entity')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->boolean('allow_comment')->default(true);
            $table->boolean('allow_share')->default(true);
            $table->string('visibility')->default('public');
            $table->dateTime('publish_at')->nullable();
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

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->foreignId('comment_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->string('file_type')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('comment_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('message_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_news_feed_works_with_subscriptions_table_without_deleted_at_column(): void
    {
        $viewer = User::create(['email' => 'viewer@example.com', 'username' => 'viewer', 'password' => 'password']);
        $author = User::create(['email' => 'author@example.com', 'username' => 'author', 'password' => 'password']);
        $followedPost = Comment::create(['comment_content' => 'Followed post', 'type' => 'post', 'user_id' => $author->id]);
        Comment::create(['comment_content' => 'Other post', 'type' => 'post', 'user_id' => $viewer->id]);
        Subscription::create(['user_id' => $author->id, 'follower_id' => $viewer->id]);

        $response = $this->getJson("/api/v1/comment/news-feed?user_id={$viewer->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $followedPost->id)
            ->assertJsonPath('data.0.user.id', $author->id)
            ->assertJsonPath('count', 2);
    }

    public function test_news_feed_only_returns_comments_visible_to_user(): void
    {
        $viewer = User::create(['email' => 'viewer@example.com', 'username' => 'viewer', 'password' => 'password']);
        $followedAuthor = User::create(['email' => 'followed@example.com', 'username' => 'followed', 'password' => 'password']);
        $otherAuthor = User::create(['email' => 'other@example.com', 'username' => 'other', 'password' => 'password']);

        $publicPost = Comment::create(['comment_content' => 'Public post', 'type' => 'post', 'visibility' => 'public', 'user_id' => $otherAuthor->id]);
        $followersPost = Comment::create(['comment_content' => 'Followers post', 'type' => 'post', 'visibility' => 'followers', 'user_id' => $followedAuthor->id]);
        $ownPrivateFuturePost = Comment::create([
            'comment_content' => 'Own private future post',
            'type' => 'post',
            'visibility' => 'private',
            'publish_at' => now()->addDay(),
            'user_id' => $viewer->id,
        ]);

        Comment::create(['comment_content' => 'Hidden followers post', 'type' => 'post', 'visibility' => 'followers', 'user_id' => $otherAuthor->id]);
        Comment::create(['comment_content' => 'Private post', 'type' => 'post', 'visibility' => 'private', 'user_id' => $otherAuthor->id]);
        Comment::create([
            'comment_content' => 'Future post',
            'type' => 'post',
            'visibility' => 'public',
            'publish_at' => now()->addDay(),
            'user_id' => $otherAuthor->id,
        ]);
        Subscription::create(['user_id' => $followedAuthor->id, 'follower_id' => $viewer->id]);

        $response = $this->getJson("/api/v1/comment/news-feed?user_id={$viewer->id}");

        $commentIds = collect($response->json('data'))->pluck('id')->all();

        $response->assertOk()->assertJsonPath('count', 3);
        $this->assertContains($publicPost->id, $commentIds);
        $this->assertContains($followersPost->id, $commentIds);
        $this->assertContains($ownPrivateFuturePost->id, $commentIds);
    }

    public function test_comment_index_without_user_only_returns_public_published_comments(): void
    {
        $author = User::create(['email' => 'author@example.com', 'username' => 'author', 'password' => 'password']);
        $publicPost = Comment::create(['comment_content' => 'Public post', 'type' => 'post', 'visibility' => 'public', 'user_id' => $author->id]);

        Comment::create(['comment_content' => 'Followers post', 'type' => 'post', 'visibility' => 'followers', 'user_id' => $author->id]);
        Comment::create(['comment_content' => 'Private post', 'type' => 'post', 'visibility' => 'private', 'user_id' => $author->id]);
        Comment::create([
            'comment_content' => 'Future post',
            'type' => 'post',
            'visibility' => 'public',
            'publish_at' => now()->addDay(),
            'user_id' => $author->id,
        ]);

        $response = $this->getJson('/api/v1/comment');

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $publicPost->id);
    }
}
