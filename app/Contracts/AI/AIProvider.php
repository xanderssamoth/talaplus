<?php

namespace App\Contracts\AI;

use App\Data\AI\AIMessageData;
use App\Data\AI\AIResponse;

interface AIProvider
{
    /**
     * @param  array<int, AIMessageData>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AIResponse;

    /**
     * @param  array<int, AIMessageData>  $messages
     * @param  array<string, mixed>  $options
     * @return iterable<int, mixed>
     */
    public function stream(array $messages, array $options = []): iterable;

    /**
     * @param  array<int, string>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function embeddings(array $input, array $options = []): array;

    /**
     * @return array{
     *     success: bool,
     *     message: string
     * }
     */
    public function isAvailable(): array;
}
