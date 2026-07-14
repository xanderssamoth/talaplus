<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ChatRequest;
use App\Http\Resources\Api\AI\ChatResource;
use App\Services\AI\AIService;

class AIController extends Controller
{
    public function __construct(
        private AIService $aiService,
    ) {}

    public function chat(ChatRequest $request): ChatResource
    {
        $message = $this->aiService->chat(
            $request->user(),
            $request->toData(),
        );

        return new ChatResource($message);
    }
}