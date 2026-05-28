<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\Hashtag;
use App\Models\History;
use App\Models\Media;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'notifications',
            'reactions',
            'reports',
            'histories',
            'subscriptions',
            'hashtag_media',
            'category_media',
            'role_user',
            'files',
            'hashtags',
            'categories',
            'roles',
            'medias',
            'pricings',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('christian_preference')->default(false);
            $table->string('status')->default('created');
            $table->string('type')->default('uncertified');
            $table->timestamps();
            $table->softDeletes();
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

        Schema::create('medias', function (Blueprint $table): void {
            $table->id();
            $table->text('media_title')->nullable();
            $table->longText('media_description')->nullable();
            $table->text('media_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('author_names')->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('for_youth')->default(false);
            $table->unsignedBigInteger('belongs_to')->nullable();
            $table->string('type')->default('music');
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
            $table->text('file_url');
            $table->longText('file_description')->nullable();
            $table->string('file_type')->default('photo');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('media_id')->nullable();
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

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('follower_id');
            $table->boolean('granted')->default(false);
            $table->timestamps();
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

        Schema::create('pricings', function (Blueprint $table): void {
            $table->id();
            $table->json('pricing_name')->nullable();
            $table->string('pricing_type')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('pricing_cost', 12, 2)->default(0);
            $table->string('currency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->foreignId('pricing_id')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('report_content')->nullable();
            $table->boolean('muted')->default(false);
            $table->foreignId('for_user_id')->nullable();
            $table->foreignId('reason_id')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    public function test_store_extracts_hashtags_mentions_users_and_notifies_admins(): void
    {
        Storage::fake('s3');
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $admin = User::create(['email' => 'admin@example.com', 'password' => 'password']);
        $mentioned = User::create(['email' => 'mentioned@example.com', 'username' => 'donaldTrump', 'password' => 'password']);
        $role = Role::create(['role_name' => ['fr' => 'Administrateur', 'en' => 'Administrator']]);
        $role->users()->attach($admin->id);

        $response = $this->post('/api/v1/media', [
            'media_title' => 'Song',
            'media_description' => 'A new #gospel song for @donaldTrump',
            'media_url' => UploadedFile::fake()->create('song.mp4', 100, 'video/mp4'),
            'cover_url' => UploadedFile::fake()->image('cover.jpg'),
            'type' => 'music',
            'price' => 0,
            'user_id' => $owner->id,
            'files' => [
                UploadedFile::fake()->create('lyrics.pdf', 10, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('success', true);
        $media = Media::query()->firstOrFail();
        $this->assertTrue(Hashtag::query()->where('keyword', 'gospel')->exists());
        Storage::disk('s3')->assertExists('medias/videos/'.basename((string) $media->media_url));
        Storage::disk('s3')->assertExists('medias/covers/'.basename((string) $media->cover_url));
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'media_created')
            ->where('from_user_id', $owner->id)
            ->where('to_user_id', $admin->id)
            ->exists());
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'mention')
            ->where('from_user_id', $owner->id)
            ->where('to_user_id', $mentioned->id)
            ->whereNotNull('media_id')
            ->exists());
        $this->assertTrue(History::query()
            ->where('entity', 'media')
            ->where('action', 'mention')
            ->where('word', 'donaldTrump')
            ->where('user_id', $owner->id)
            ->exists());
    }

    public function test_store_requires_video_media_url_and_image_cover_url(): void
    {
        Storage::fake('s3');
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);

        $response = $this->post('/api/v1/media', [
            'media_title' => 'Invalid media',
            'media_description' => 'Wrong files',
            'media_url' => UploadedFile::fake()->image('not-video.jpg'),
            'cover_url' => UploadedFile::fake()->create('not-image.pdf', 10, 'application/pdf'),
            'type' => 'music',
            'price' => 0,
            'user_id' => $owner->id,
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['media_url', 'cover_url']);
    }

    public function test_update_media_resyncs_hashtags_and_mentions(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $oldMention = User::create(['email' => 'old@example.com', 'username' => 'oldUser', 'password' => 'password']);
        $newMention = User::create(['email' => 'new@example.com', 'username' => 'newUser', 'password' => 'password']);
        $media = Media::create([
            'media_title' => 'Song',
            'media_description' => '#old @oldUser',
            'type' => 'music',
            'price' => 0,
            'user_id' => $owner->id,
        ]);
        $oldHashtag = Hashtag::create(['keyword' => 'old']);
        $media->hashtags()->attach($oldHashtag->id);
        AdminNotification::create([
            'type' => 'mention',
            'from_user_id' => $owner->id,
            'to_user_id' => $oldMention->id,
            'media_id' => $media->id,
        ]);
        History::create([
            'word' => 'oldUser',
            'entity' => 'media',
            'entity_id' => $media->id,
            'action' => 'mention',
            'user_id' => $owner->id,
        ]);

        $this->patchJson("/api/v1/media/{$media->id}", ['media_description' => '#new @newUser'])
            ->assertOk();

        $this->assertFalse(AdminNotification::query()
            ->where('type', 'mention')
            ->where('to_user_id', $oldMention->id)
            ->where('media_id', $media->id)
            ->exists());
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'mention')
            ->where('to_user_id', $newMention->id)
            ->where('media_id', $media->id)
            ->exists());
        $this->assertTrue($media->refresh()->hashtags()->where('keyword', 'new')->exists());
        $this->assertFalse($media->hashtags()->where('keyword', 'old')->exists());
    }

    public function test_publish_media_notifies_followers(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $follower = User::create(['email' => 'follower@example.com', 'password' => 'password']);
        $media = Media::create(['media_title' => 'Song', 'type' => 'music', 'price' => 0, 'user_id' => $owner->id]);
        Subscription::create(['user_id' => $owner->id, 'follower_id' => $follower->id]);

        $response = $this->patchJson("/api/v1/media/{$media->id}/publish");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($media->refresh()->is_shared);
        $this->assertTrue(AdminNotification::query()->where('type', 'media_published')->where('to_user_id', $follower->id)->exists());
    }

    public function test_filter_medias_can_search_by_word(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        Media::create(['media_title' => 'Morning Worship', 'media_description' => 'Calm song', 'type' => 'music', 'price' => 0, 'user_id' => $owner->id]);
        Media::create(['media_title' => 'Business Talk', 'media_description' => 'Market update', 'type' => 'business', 'price' => 0, 'user_id' => $owner->id]);

        $response = $this->getJson('/api/v1/media/filter/list?word=Worship');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.media_title.en', 'Morning Worship');
    }

    public function test_show_records_media_view_history(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $viewer = User::create(['email' => 'viewer@example.com', 'password' => 'password']);
        $media = Media::create(['media_title' => 'Song', 'type' => 'music', 'price' => 0, 'user_id' => $owner->id]);

        $response = $this->getJson("/api/v1/media/{$media->id}?user_id={$viewer->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(History::query()->where('entity', 'media')->where('action', 'view')->where('entity_id', $media->id)->where('user_id', $viewer->id)->exists());
    }

    public function test_like_gift_and_report_create_records_and_notifications(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $actor = User::create(['email' => 'actor@example.com', 'password' => 'password']);
        $media = Media::create(['media_title' => 'Song', 'type' => 'music', 'price' => 0, 'user_id' => $owner->id]);

        $this->postJson("/api/v1/media/{$media->id}/like", ['user_id' => $actor->id])->assertOk();

        $pricingId = \DB::table('pricings')->insertGetId([
            'pricing_name' => json_encode(['fr' => 'Cadeau']),
            'pricing_type' => 'gift',
            'reason' => 'gift',
            'pricing_cost' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/media/{$media->id}/gift", ['user_id' => $actor->id, 'pricing_id' => $pricingId])->assertOk();
        $this->postJson("/api/v1/media/{$media->id}/report/{$actor->id}", ['report_content' => 'Bad'])->assertOk();

        $this->assertSame(2, Reaction::query()->count());
        $this->assertSame(1, Report::query()->count());
        $this->assertTrue(AdminNotification::query()->where('type', 'like_sent')->exists());
        $this->assertTrue(AdminNotification::query()->where('type', 'gift_sent')->exists());
        $this->assertTrue(AdminNotification::query()->where('type', 'report_sent')->exists());
    }

    public function test_media_reaction_can_be_removed(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $actor = User::create(['email' => 'actor@example.com', 'password' => 'password']);
        $media = Media::create(['media_title' => 'Song', 'type' => 'music', 'price' => 0, 'user_id' => $owner->id]);

        $this->postJson("/api/v1/media/{$media->id}/like", ['user_id' => $actor->id])->assertOk();
        $this->postJson("/api/v1/media/{$media->id}/like", ['user_id' => $actor->id, 'action' => 'remove'])->assertOk();

        $this->assertSame(0, Reaction::query()->where('type', 'like')->where('media_id', $media->id)->count());
        $this->assertFalse(History::query()->where('entity', 'media')->where('action', 'like')->where('entity_id', $media->id)->exists());
        $this->assertFalse(AdminNotification::query()->where('type', 'like_sent')->where('media_id', $media->id)->exists());
    }
}
