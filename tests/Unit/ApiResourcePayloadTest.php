<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ApiResourceController;
use App\Http\Resources\Api\ApiResource;
use App\Models\Subscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiResourcePayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('subscriptions');
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('follower_id');
            $table->boolean('granted')->default(false);
            $table->timestamps();
        });
    }

    public function test_post_payload_keeps_database_defaults_when_a_client_sends_null(): void
    {
        $payload = $this->controller()->extract(Request::create('/', 'POST', [
            'user_id' => 1,
            'follower_id' => 2,
            'granted' => null,
        ]));

        $this->assertSame(['user_id' => 1, 'follower_id' => 2], $payload);
    }

    public function test_update_payload_keeps_an_explicit_null_for_nullable_updates(): void
    {
        $payload = $this->controller()->extract(Request::create('/', 'PATCH', [
            'granted' => null,
        ]));

        $this->assertSame(['granted' => null], $payload);
    }

    private function controller(): ApiResourceController
    {
        return new class extends ApiResourceController
        {
            protected string $modelClass = Subscription::class;

            protected string $resourceClass = ApiResource::class;

            /**
             * @return array<string, mixed>
             */
            public function extract(Request $request): array
            {
                return $this->payload($request);
            }
        };
    }
}
