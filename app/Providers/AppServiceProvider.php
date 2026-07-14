<?php

namespace App\Providers;

use App\Contracts\AI\AIProvider;
use App\Helpers\ApiStoreColumnsExtension;
use App\Services\AI\OpenAIService;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIProvider::class, OpenAIService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::configure()->withOperationTransformers(
            fn (OperationTransformers $transformers) => $transformers->append(ApiStoreColumnsExtension::class)
        );
    }
}
