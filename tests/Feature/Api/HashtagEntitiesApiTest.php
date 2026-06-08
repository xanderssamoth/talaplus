<?php

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Hashtag;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HashtagEntitiesApiTest extends TestCase
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
            'hashtag_comment',
            'hashtag_media',
            'category_media',
            'categories',
            'hashtags',
            'comments',
            'files',
            'reactions',
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
            $table->timestamps();
            $table->softDeletes();
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
            $table->string('type')->default('music');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->longText('comment_content')->nullable();
            $table->string('type')->nullable();
            $table->string('for_entity')->nullable();
            $table->unsignedBigInteger('answered_for')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hashtags', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword');
            $table->timestamps();
        });

        Schema::create('hashtag_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hashtag_id');
            $table->foreignId('media_id');
            $table->timestamps();
        });

        Schema::create('hashtag_comment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hashtag_id');
            $table->foreignId('comment_id');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->json('category_name');
            $table->json('category_description')->nullable();
            $table->string('for_type');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('media_id');
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->text('file_url');
            $table->foreignId('comment_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('message_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->foreignId('media_id')->nullable();
            $table->foreignId('comment_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_entities_returns_medias_and_comments_for_hashtag(): void
    {
        $user = User::create(['email' => 'author@example.com', 'username' => 'author', 'password' => 'password']);
        $hashtag = Hashtag::create(['keyword' => 'gospel']);

        $pivotMedia = Media::create(['media_title' => ['fr' => 'Chant'], 'media_description' => 'Description', 'user_id' => $user->id]);
        $textMedia = Media::create(['media_title' => ['fr' => 'Louange'], 'media_description' => 'Video #gospel', 'user_id' => $user->id]);
        Media::create(['media_title' => ['fr' => 'Sport'], 'media_description' => 'Video #sport', 'user_id' => $user->id]);
        $pivotMedia->hashtags()->attach($hashtag->id);

        $pivotComment = Comment::create(['comment_content' => 'Commentaire', 'user_id' => $user->id]);
        $textComment = Comment::create(['comment_content' => 'Post #gospel', 'user_id' => $user->id]);
        Comment::create(['comment_content' => 'Post #sport', 'user_id' => $user->id]);
        $pivotComment->hashtags()->attach($hashtag->id);

        $response = $this->getJson('/api/v1/hashtag/gospel/entities');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hashtag.keyword', 'gospel')
            ->assertJsonCount(2, 'data.medias')
            ->assertJsonCount(2, 'data.comments');

        $mediaIds = collect($response->json('data.medias'))->pluck('id')->all();
        $commentIds = collect($response->json('data.comments'))->pluck('id')->all();

        $this->assertContains($pivotMedia->id, $mediaIds);
        $this->assertContains($textMedia->id, $mediaIds);
        $this->assertContains($pivotComment->id, $commentIds);
        $this->assertContains($textComment->id, $commentIds);
    }
}
