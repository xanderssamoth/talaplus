<?php

namespace App\Services\AI;

class AISettingService
{
    public function getProvider(): ?string
    {
        return null;
    }

    public function getModel(): ?string
    {
        return null;
    }

    public function getTemperature(): ?float
    {
        return null;
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function isStreamingEnabled(): bool
    {
        return false;
    }

    /**
     * Retourne toute la configuration IA.
     *
     * @return array{
     *     provider: string,
     *     model: string,
     *     temperature: float,
     *     max_tokens: int,
     *     stream: bool
     * }
     */
    public function getConfiguration(): array
    {
        return [
            'provider' => $this->getProvider() ?? 'openai',
            'model' => $this->getModel() ?? env('OPENAI_MODEL', 'gpt-5.5'),
            'temperature' => $this->getTemperature() ?? 0.7,
            'max_tokens' => $this->getMaxTokens() ?? 2000,
            'stream' => $this->isStreamingEnabled(),
        ];
    }
}
