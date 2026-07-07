<?php

namespace App\Console\Commands;

use App\Data\AI\AIMessageData;
use App\Services\AI\OpenAIService;
use Illuminate\Console\Command;
use Throwable;

class AITestCommand extends Command
{
    protected $signature = 'ai:test';

    protected $description = 'Teste la connexion OpenAI';

    public function handle(OpenAIService $openAI): int
    {
        $this->info('Connexion à OpenAI...');

        $status = $openAI->isAvailable();

        if (! $status['success']) {
            $this->error($status['message']);

            return self::FAILURE;
        }

        $this->info('Connexion réussie.');

        try {

            $response = $openAI->chat([
                new AIMessageData(
                    role: 'user',
                    content: 'Dis simplement : Bonjour TALA+'
                ),
            ]);

            $this->newLine();

            $this->info('Réponse :');

            $this->line($response->content);

            return self::SUCCESS;

        } catch (Throwable $exception) {

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}