<?php

namespace App\Console\Commands;

use App\Contracts\AI\AIProvider;
use App\Data\AI\AIMessageData;
use Illuminate\Console\Command;
use Throwable;

class AITestCommand extends Command
{
    protected $signature = 'ai:test';
    protected $description = 'Teste la connexion avec le fournisseur IA configuré';

    public function handle(AIProvider $aIProvider): int
    {
        $this->info('Connexion au fournisseur IA...');

        $status = $aIProvider->isAvailable();

        if (! $status['success']) {
            $this->error($status['message']);

            return self::FAILURE;
        }

        $this->info('Connexion réussie.');

        try {
            $appName = config('app.name');
            $response = $aIProvider->chat([
                new AIMessageData(
                    role: 'user',
                    content: "Dis simplement : Bonjour {$appName}"
                ),
            ]);

            $this->newLine();
            $this->info('Réponse du modèle :');
            $this->line($response->content);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
