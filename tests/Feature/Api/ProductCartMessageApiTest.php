<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\Comment;
use App\Models\CustomerOrder;
use App\Models\File;
use App\Models\Group;
use App\Models\History;
use App\Models\Message;
use App\Models\Product;
use App\Models\Reaction;
use App\Models\Role;
use App\Models\Specification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCartMessageApiTest extends TestCase
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
            'bank_cards',
            'blocked_users',
            'notifications',
            'reactions',
            'reports',
            'histories',
            'money_transfers',
            'payments',
            'promo_codes',
            'customer_orders',
            'carts',
            'files',
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
            'medias',
            'pricings',
            'pricing_descriptions',
            'reasons',
            'password_resets',
            'personal_access_tokens',
            'sessions',
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

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->string('type')->default('product');
            $table->integer('quantity')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('action')->default('sale');
            $table->boolean('is_shared')->default(false);
            $table->dateTime('price_reduction_start')->nullable();
            $table->dateTime('price_reduction_end')->nullable();
            $table->decimal('reduction_rate', 3, 2)->nullable();
            $table->foreignId('category_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('specifications', function (Blueprint $table): void {
            $table->id();
            $table->text('spec_content');
            $table->foreignId('product_id');
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

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->longText('file_description')->nullable();
            $table->string('file_type')->default('photo');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('message_id')->nullable();
            $table->foreignId('comment_id')->nullable();
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

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->smallInteger('number_of_stars')->nullable();
            $table->foreignId('pricing_id')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('comment_id')->nullable();
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

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->longText('comment_content')->nullable();
            $table->string('type')->nullable();
            $table->string('for_entity')->nullable();
            $table->unsignedBigInteger('answered_for')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hashtags', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword');
            $table->timestamps();
        });

        Schema::create('hashtag_comment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hashtag_id');
            $table->foreignId('comment_id');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('follower_id');
            $table->boolean('granted')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->text('payment_code')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_orders', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price_at_that_time', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->integer('quantity')->nullable();
            $table->foreignId('product_id');
            $table->foreignId('cart_id');
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

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->longText('message_content')->nullable();
            $table->unsignedBigInteger('answered_for')->nullable();
            $table->string('status')->default('unread');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('addressee_user_id')->nullable();
            $table->foreignId('addressee_group_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_product_store_publish_rate_and_report(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $admin = User::create(['email' => 'admin@example.com', 'password' => 'password']);
        $role = Role::create(['role_name' => ['fr' => 'Administrateur', 'en' => 'Administrator']]);
        $role->users()->attach($admin->id);

        $response = $this->postJson('/api/v1/product', [
            'product_name' => 'Book',
            'price' => 10,
            'currency' => 'USD',
            'quantity' => 5,
            'user_id' => $owner->id,
            'price_reduction_start' => now()->toDateTimeString(),
            'price_reduction_end' => now()->addDay()->toDateTimeString(),
            'reduction_rate' => 0.2,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $product = Product::query()->firstOrFail();
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'product_added')
            ->where('from_user_id', $owner->id)
            ->where('to_user_id', $admin->id)
            ->where('product_id', $product->id)
            ->exists());
        $this->assertTrue(History::query()
            ->where('entity', 'product')
            ->where('entity_id', $product->id)
            ->where('action', 'post')
            ->where('user_id', $owner->id)
            ->exists());

        $this->patchJson("/api/v1/product/{$product->id}/publish")->assertOk();
        $this->assertTrue($product->refresh()->is_shared);
        $this->assertTrue(AdminNotification::query()->where('type', 'product_accepted')->where('to_user_id', $owner->id)->exists());

        $this->postJson("/api/v1/product/{$product->id}/rate", ['user_id' => $admin->id, 'number_of_stars' => 4])->assertOk();
        $this->postJson("/api/v1/product/{$product->id}/report/{$admin->id}", ['report_content' => 'Bad'])->assertOk();

        $this->assertTrue(Reaction::query()->where('type', 'star')->where('number_of_stars', 4)->exists());
        $this->assertTrue(AdminNotification::query()->where('type', 'report_sent')->where('product_id', $product->id)->exists());
    }

    public function test_product_json_includes_specifications_reviews_and_promotion(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $reviewer = User::create(['email' => 'reviewer@example.com', 'password' => 'password']);
        $product = Product::create([
            'product_name' => 'Book',
            'price' => 100,
            'currency' => 'USD',
            'user_id' => $owner->id,
            'price_reduction_end' => now()->addDay(),
            'reduction_rate' => 0.25,
        ]);
        Specification::create(['spec_content' => 'Hard cover', 'product_id' => $product->id]);
        Reaction::create(['type' => 'star', 'number_of_stars' => 5, 'product_id' => $product->id, 'user_id' => $reviewer->id]);

        $response = $this->getJson("/api/v1/product/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.specifications.0.spec_content', 'Hard cover')
            ->assertJsonPath('data.reviews.total', 1)
            ->assertJsonPath('data.reviews.five_stars', 1)
            ->assertJsonPath('data.promotion.reduced_price', 75);
    }

    public function test_product_store_saves_uploaded_files_to_s3(): void
    {
        Storage::fake('s3');
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);

        $response = $this->post('/api/v1/product', [
            'product_name' => 'Book',
            'price' => 10,
            'currency' => 'USD',
            'user_id' => $owner->id,
            'files' => [
                UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $file = File::query()->where('file_name', 'manual.pdf')->firstOrFail();

        $this->assertSame($owner->id, $file->user_id);
        $this->assertNotNull($file->product_id);
        Storage::disk('s3')->assertExists('products/files/'.basename((string) $file->file_url));
    }

    public function test_filter_products_can_search_by_word(): void
    {
        Product::create(['product_name' => 'Blue Book', 'price' => 10, 'currency' => 'USD']);
        Product::create(['product_name' => 'Red Shirt', 'price' => 15, 'currency' => 'USD']);

        $response = $this->getJson('/api/v1/product/filter/list?word=Blue');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Blue Book');
    }

    public function test_product_comment_creates_notification_and_history(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $commenter = User::create(['email' => 'commenter@example.com', 'password' => 'password']);
        $product = Product::create(['product_name' => 'Book', 'price' => 10, 'currency' => 'USD', 'user_id' => $owner->id]);

        $this->postJson('/api/v1/comment', [
            'comment_content' => 'Nice',
            'product_id' => $product->id,
            'user_id' => $commenter->id,
        ])->assertOk();

        $this->assertTrue(AdminNotification::query()
            ->where('type', 'comment_sent')
            ->where('from_user_id', $commenter->id)
            ->where('to_user_id', $owner->id)
            ->where('product_id', $product->id)
            ->exists());
        $this->assertTrue(History::query()
            ->where('entity', 'product')
            ->where('entity_id', $product->id)
            ->where('action', 'comment')
            ->where('user_id', $commenter->id)
            ->exists());
    }

    public function test_post_comment_notifies_followers_and_creates_post_history(): void
    {
        $owner = User::create(['email' => 'poster@example.com', 'password' => 'password']);
        $follower = User::create(['email' => 'follower@example.com', 'password' => 'password']);
        Subscription::create(['user_id' => $owner->id, 'follower_id' => $follower->id, 'granted' => true]);

        $response = $this->postJson('/api/v1/comment', [
            'comment_content' => 'My post',
            'type' => 'post',
            'for_entity' => 'user',
            'user_id' => $owner->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'post')
            ->assertJsonPath('data.likes_count', 0);

        $comment = Comment::query()->firstOrFail();
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'post_sent')
            ->where('from_user_id', $owner->id)
            ->where('to_user_id', $follower->id)
            ->where('comment_id', $comment->id)
            ->exists());
        $this->assertTrue(History::query()
            ->where('entity', 'comment')
            ->where('entity_id', $comment->id)
            ->where('action', 'post')
            ->where('user_id', $owner->id)
            ->exists());
    }

    public function test_comment_like_creates_reaction_notification_history_and_count(): void
    {
        $owner = User::create(['email' => 'poster@example.com', 'password' => 'password']);
        $liker = User::create(['email' => 'liker@example.com', 'password' => 'password']);
        $comment = Comment::create([
            'comment_content' => 'Post',
            'type' => 'post',
            'user_id' => $owner->id,
        ]);

        $this->postJson("/api/v1/comment/{$comment->id}/like", ['user_id' => $liker->id])
            ->assertOk()
            ->assertJsonPath('data.type', 'like')
            ->assertJsonPath('data.comment_id', $comment->id);

        $this->getJson("/api/v1/comment/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('data.likes_count', 1);

        $this->getJson("/api/v1/comment/{$comment->id}/like")
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertTrue(AdminNotification::query()
            ->where('type', 'like_sent')
            ->where('from_user_id', $liker->id)
            ->where('to_user_id', $owner->id)
            ->where('comment_id', $comment->id)
            ->exists());
        $this->assertTrue(History::query()
            ->where('entity', 'comment')
            ->where('entity_id', $comment->id)
            ->where('action', 'like')
            ->where('user_id', $liker->id)
            ->exists());
    }

    public function test_comment_store_and_update_sync_hashtags_and_mentions(): void
    {
        $owner = User::create(['email' => 'poster@example.com', 'username' => 'poster', 'password' => 'password']);
        $oldMention = User::create(['email' => 'old@example.com', 'username' => 'oldUser', 'password' => 'password']);
        $newMention = User::create(['email' => 'new@example.com', 'username' => 'newUser', 'password' => 'password']);

        $response = $this->postJson('/api/v1/comment', [
            'comment_content' => 'Hello #old @oldUser',
            'type' => 'post',
            'user_id' => $owner->id,
        ]);

        $response->assertOk();
        $comment = Comment::query()->firstOrFail();
        $this->assertTrue($comment->hashtags()->where('keyword', 'old')->exists());
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'mention')
            ->where('from_user_id', $owner->id)
            ->where('to_user_id', $oldMention->id)
            ->where('comment_id', $comment->id)
            ->exists());

        $this->patchJson("/api/v1/comment/{$comment->id}", [
            'comment_content' => 'Hello #new @newUser',
        ])->assertOk();

        $this->assertFalse($comment->refresh()->hashtags()->where('keyword', 'old')->exists());
        $this->assertTrue($comment->hashtags()->where('keyword', 'new')->exists());
        $this->assertFalse(AdminNotification::query()
            ->where('type', 'mention')
            ->where('to_user_id', $oldMention->id)
            ->where('comment_id', $comment->id)
            ->exists());
        $this->assertTrue(AdminNotification::query()
            ->where('type', 'mention')
            ->where('to_user_id', $newMention->id)
            ->where('comment_id', $comment->id)
            ->exists());
        $this->assertFalse(History::query()
            ->where('entity', 'comment')
            ->where('action', 'mention')
            ->where('word', 'oldUser')
            ->where('entity_id', $comment->id)
            ->exists());
    }

    public function test_comment_store_and_update_save_uploaded_files(): void
    {
        Storage::fake('s3');
        $owner = User::create(['email' => 'poster@example.com', 'username' => 'poster', 'password' => 'password']);

        $response = $this->post('/api/v1/comment', [
            'comment_content' => 'Post with file',
            'type' => 'post',
            'user_id' => $owner->id,
            'files' => [
                UploadedFile::fake()->create('first.jpg', 10, 'image/jpeg'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.files.0.file_name', 'first.jpg')
            ->assertJsonPath('data.files.0.file_type', 'photo');

        $comment = Comment::query()->firstOrFail();
        $this->assertTrue(File::query()
            ->where('comment_id', $comment->id)
            ->where('user_id', $owner->id)
            ->where('file_name', 'first.jpg')
            ->exists());
        Storage::disk('s3')->assertExists('comments/files/'.basename((string) $response->json('data.files.0.file_url')));

        $this->patch('/api/v1/comment/'.$comment->id, [
            'files' => [
                UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
            ],
            'file_description' => 'Attachment',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(2, 'data.files');

        $this->assertTrue(File::query()
            ->where('comment_id', $comment->id)
            ->where('file_name', 'second.pdf')
            ->where('file_description', 'Attachment')
            ->where('file_type', 'document')
            ->exists());
    }

    public function test_comment_like_can_be_removed(): void
    {
        $owner = User::create(['email' => 'poster@example.com', 'password' => 'password']);
        $liker = User::create(['email' => 'liker@example.com', 'password' => 'password']);
        $comment = Comment::create([
            'comment_content' => 'Post',
            'type' => 'post',
            'user_id' => $owner->id,
        ]);

        $this->postJson("/api/v1/comment/{$comment->id}/like", ['user_id' => $liker->id])->assertOk();
        $this->postJson("/api/v1/comment/{$comment->id}/like", ['user_id' => $liker->id, 'action' => 'remove'])->assertOk();

        $this->assertSame(0, Reaction::query()->where('type', 'like')->where('comment_id', $comment->id)->count());
        $this->assertFalse(History::query()->where('entity', 'comment')->where('action', 'like')->where('entity_id', $comment->id)->exists());
        $this->assertFalse(AdminNotification::query()->where('type', 'like_sent')->where('comment_id', $comment->id)->exists());
    }

    public function test_product_star_can_be_removed(): void
    {
        $owner = User::create(['email' => 'owner@example.com', 'password' => 'password']);
        $actor = User::create(['email' => 'actor@example.com', 'password' => 'password']);
        $product = Product::create(['product_name' => 'Book', 'price' => 10, 'currency' => 'USD', 'user_id' => $owner->id]);

        $this->postJson("/api/v1/product/{$product->id}/rate", ['user_id' => $actor->id, 'number_of_stars' => 5])->assertOk();
        $this->postJson("/api/v1/product/{$product->id}/rate", ['user_id' => $actor->id, 'action' => 'remove'])->assertOk();

        $this->assertSame(0, Reaction::query()->where('type', 'star')->where('product_id', $product->id)->count());
        $this->assertFalse(History::query()->where('entity', 'product')->whereIn('action', ['star', 'like'])->where('entity_id', $product->id)->exists());
    }

    public function test_comment_news_feed_prioritizes_interacted_followed_then_recent_posts(): void
    {
        $currentUser = User::create(['email' => 'current@example.com', 'password' => 'password']);
        $followedUser = User::create(['email' => 'followed@example.com', 'password' => 'password']);
        $likedOwner = User::create(['email' => 'liked-owner@example.com', 'password' => 'password']);
        $commentedOwner = User::create(['email' => 'commented-owner@example.com', 'password' => 'password']);
        $otherUser = User::create(['email' => 'other@example.com', 'password' => 'password']);

        $likedPost = Comment::create(['comment_content' => 'Liked post', 'type' => 'post', 'user_id' => $likedOwner->id]);
        $commentedPost = Comment::create(['comment_content' => 'Commented post', 'type' => 'post', 'user_id' => $commentedOwner->id]);
        $followedPost = Comment::create(['comment_content' => 'Followed post', 'type' => 'post', 'user_id' => $followedUser->id]);
        $otherPost = Comment::create(['comment_content' => 'Other post', 'type' => 'post', 'user_id' => $otherUser->id]);

        Reaction::create(['type' => 'like', 'comment_id' => $likedPost->id, 'user_id' => $currentUser->id]);
        Comment::create([
            'comment_content' => 'Reply',
            'type' => 'comment',
            'answered_for' => $commentedPost->id,
            'user_id' => $currentUser->id,
        ]);
        Subscription::create(['user_id' => $followedUser->id, 'follower_id' => $currentUser->id, 'granted' => true]);

        $response = $this->getJson("/api/v1/comment/news-feed?user_id={$currentUser->id}");

        $response->assertOk()
            ->assertJsonPath('count', 4)
            ->assertJsonPath('data.0.id', $commentedPost->id)
            ->assertJsonPath('data.1.id', $likedPost->id)
            ->assertJsonPath('data.2.id', $followedPost->id)
            ->assertJsonPath('data.3.id', $otherPost->id);
    }

    public function test_add_remove_and_check_cart_restores_product_quantity(): void
    {
        $user = User::create(['email' => 'buyer@example.com', 'password' => 'password']);
        $product = Product::create(['product_name' => 'Book', 'price' => 10, 'currency' => 'USD', 'quantity' => 5]);

        $this->postJson('/api/v1/cart/add', ['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->assertSame(3, $product->refresh()->quantity);
        $this->assertSame(1, CustomerOrder::query()->count());

        $this->getJson("/api/v1/cart/is-in-cart?user_id={$user->id}&product_id={$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_in_cart', true);

        $this->deleteJson('/api/v1/cart/remove', ['user_id' => $user->id, 'product_id' => $product->id])->assertOk();
        $this->assertSame(5, $product->refresh()->quantity);
        $this->assertSame(0, CustomerOrder::query()->count());
    }

    public function test_message_search_and_conversation_endpoints(): void
    {
        $sender = User::create(['firstname' => 'Sender', 'email' => 'sender@example.com', 'password' => 'password']);
        $receiver = User::create(['firstname' => 'Receiver', 'email' => 'receiver@example.com', 'password' => 'password']);
        Message::create(['message_content' => 'Hello there', 'user_id' => $sender->id, 'addressee_user_id' => $receiver->id]);
        Message::create(['message_content' => 'Hi back', 'user_id' => $receiver->id, 'addressee_user_id' => $sender->id]);

        $this->getJson("/api/v1/message/search/by-word?user_id={$sender->id}&word=Hello")->assertOk();
        $this->getJson("/api/v1/message/conversation?user_id={$sender->id}")->assertOk()->assertJsonPath('success', true);
        $this->getJson("/api/v1/message/conversation/users?user_id={$sender->id}&addressee_user_id={$receiver->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $group = Group::create(['group_name' => 'Team', 'user_id' => $sender->id]);
        $group->users()->attach($sender->id);
        Message::create(['message_content' => 'Group hello', 'user_id' => $sender->id, 'addressee_group_id' => $group->id]);

        $this->getJson("/api/v1/message/conversation/group?user_id={$sender->id}&group_id={$group->id}")
            ->assertOk()
            ->assertJsonPath('data.is_member', true);
    }
}
