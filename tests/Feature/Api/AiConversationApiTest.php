<?php

namespace Tests\Feature\Api;

use App\Models\AI\AiConversation;
use App\Models\AI\AiMessage;
use App\Models\AI\AiMessageFile;
use App\Models\AI\AiToolCall;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiConversationApiTest extends TestCase
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
            'ai_tool_calls',
            'ai_message_files',
            'ai_messages',
            'ai_conversations',
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
            'media_progresses',
            'media_user',
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
            $table->text('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->longText('file_description')->nullable();
            $table->string('file_type')->default('photo');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('assistant', 50);
            $table->longText('system_prompt')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('user_id');
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('role');
            $table->longText('content');
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->foreignId('conversation_id');
        });

        Schema::create('ai_message_files', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('ai_message_id');
            $table->foreignId('file_id');
        });

        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('tool_name', 100);
            $table->json('arguments')->nullable();
            $table->json('response')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->foreignId('ai_message_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_ai_models_relations_and_api_resources_work(): void
    {
        $user = User::create(['firstname' => 'Tala', 'email' => 'tala@example.com', 'password' => 'password']);

        $conversationResponse = $this->postJson('/api/v1/ai-conversation', [
            'title' => 'Planification',
            'assistant' => 'openai',
            'system_prompt' => 'Be concise.',
            'last_message_at' => now()->toDateTimeString(),
            'user_id' => $user->id,
        ]);

        $conversationResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Planification')
            ->assertJsonPath('data.user.id', $user->id);

        $conversation = AiConversation::query()->firstOrFail();

        $messageResponse = $this->postJson('/api/v1/ai-message', [
            'role' => 'assistant',
            'content' => 'Here is a proposal.',
            'model' => 'gpt-5-mini',
            'prompt_tokens' => 12,
            'completion_tokens' => 8,
            'total_tokens' => 20,
            'response_time_ms' => 320,
            'conversation_id' => $conversation->id,
        ]);

        $messageResponse->assertOk()
            ->assertJsonPath('data.role', 'assistant')
            ->assertJsonPath('data.prompt_tokens', 12);

        $message = AiMessage::query()->firstOrFail();
        $file = File::create([
            'file_name' => 'brief.pdf',
            'file_url' => 'https://example.test/brief.pdf',
            'file_type' => 'document',
            'user_id' => $user->id,
        ]);

        $this->postJson('/api/v1/ai-message-file', [
            'ai_message_id' => $message->id,
            'file_id' => $file->id,
        ])->assertOk()
            ->assertJsonPath('data.ai_message_id', $message->id)
            ->assertJsonPath('data.file_id', $file->id);

        $toolResponse = $this->postJson('/api/v1/ai-tool-call', [
            'tool_name' => 'search_catalog',
            'arguments' => ['query' => 'films'],
            'response' => ['count' => 3],
            'status' => 'success',
            'ai_message_id' => $message->id,
        ]);

        $toolResponse->assertOk()
            ->assertJsonPath('data.arguments.query', 'films')
            ->assertJsonPath('data.response.count', 3);

        $this->assertTrue($user->aiConversations()->whereKey($conversation->id)->exists());
        $this->assertTrue($conversation->messages()->whereKey($message->id)->exists());
        $this->assertTrue($message->files()->whereKey($file->id)->exists());
        $this->assertTrue($file->aiMessages()->whereKey($message->id)->exists());
        $this->assertTrue($message->toolCalls()->where('tool_name', 'search_catalog')->exists());
        $this->assertSame($message->id, AiMessageFile::query()->firstOrFail()->aiMessage->id);
        $this->assertSame($message->id, AiToolCall::query()->firstOrFail()->aiMessage->id);
    }
}
