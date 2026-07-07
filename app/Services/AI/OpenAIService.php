<?php

namespace App\Services\AI;

use App\Data\AI\AIResponse;
use App\Mappers\AI\OpenAIMessageMapper;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class OpenAIService
{
    public function __construct(
        private AISettingService $settingService,
        private ToolService $toolService,
        private OpenAIMessageMapper $messageMapper,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return \App\Data\AI\AIResponse
     */
    public function chat(array $messages, array $options = []): AIResponse
    {
        $configuration = $this->settingService->getConfiguration();

        $payload = [
            'model' => $options['model']
                ?? $configuration['model']
                ?? 'gpt-5.5',

            'input' => $this->messageMapper->toOpenAI($messages),
        ];

        if (! empty($configuration['temperature'])) {
            $payload['temperature'] = $configuration['temperature'];
        }

        if (! empty($configuration['max_tokens'])) {
            $payload['max_output_tokens'] = $configuration['max_tokens'];
        }

        $tools = $this->toolService->listTools();

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = OpenAI::responses()->create($payload);

        return $this->messageMapper->toAIResponse($response);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return iterable<int, mixed>
     */
    public function stream(array $messages, array $options = []): iterable
    {
        return [];
    }

    /**
     * @param  array<int, string>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function embeddings(array $input, array $options = []): array
    {
        return [];
    }

    /**
     * Vérifie la connexion à OpenAI.
     *
     * @return array{
     *     success: bool,
     *     message: string
     * }
     */
    public function isAvailable(): array
    {
        try {
            // Appel léger : récupération de la liste des modèles.
            OpenAI::models()->list();

            return [
                'success' => true,
                'message' => 'Connexion à OpenAI réussie.',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
