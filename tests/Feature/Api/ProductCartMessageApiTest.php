<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotification;
use App\Models\CustomerOrder;
use App\Models\Group;
use App\Models\History;
use App\Models\Message;
use App\Models\Product;
use App\Models\Reaction;
use App\Models\Role;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCartMessageApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'notifications',
            'reactions',
            'reports',
            'histories',
            'customer_orders',
            'carts',
            'messages',
            'comments',
            'group_user',
            'groups',
            'files',
            'specifications',
            'role_user',
            'roles',
            'products',
            'categories',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('email')->nullable();
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
            $table->unsignedBigInteger('answered_for')->nullable();
            $table->foreignId('media_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id');
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
        $this->assertTrue(AdminNotification::query()->where('type', 'product_added')->where('to_user_id', $admin->id)->exists());

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
