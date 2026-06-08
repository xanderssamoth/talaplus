<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaBelongsToApiTest extends TestCase
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
            'hashtag_media',
            'category_media',
            'hashtags',
            'categories',
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
            $table->integer('media_length')->nullable();
            $table->text('media_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('author_names')->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('for_youth')->default(false);
            $table->unsignedBigInteger('belongs_to')->nullable();
            $table->string('type')->default('film_series');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_find_by_belongs_to_returns_matching_medias(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'username' => 'owner', 'password' => 'password']);
        $series = Media::create(['media_title' => ['fr' => 'Serie'], 'user_id' => $owner->id]);
        $episode = Media::create(['media_title' => ['fr' => 'Episode'], 'belongs_to' => $series->id, 'user_id' => $owner->id]);
        Media::create(['media_title' => ['fr' => 'Autre'], 'belongs_to' => null, 'user_id' => $owner->id]);
        Media::create(['media_title' => ['fr' => 'Autre serie'], 'belongs_to' => 999, 'user_id' => $owner->id]);

        $response = $this->getJson("/api/v1/media/belongs-to/{$series->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $episode->id)
            ->assertJsonPath('data.0.belongs_to', $series->id)
            ->assertJsonPath('count', 1);
    }

    public function test_show_returns_media_when_files_table_has_no_media_id_column(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'username' => 'owner', 'password' => 'password']);
        $media = Media::create(['media_title' => ['fr' => 'Episode'], 'user_id' => $owner->id]);

        $response = $this->getJson("/api/v1/media/{$media->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $media->id);
    }
}
