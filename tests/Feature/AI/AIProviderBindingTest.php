<?php

namespace Tests\Feature\AI;

use App\Contracts\AI\AIProvider;
use App\Services\AI\OpenAIService;
use Tests\TestCase;

class AIProviderBindingTest extends TestCase
{
    public function test_ai_provider_contract_resolves_to_open_ai_service(): void
    {
        $this->assertInstanceOf(OpenAIService::class, $this->app->make(AIProvider::class));
    }
}
