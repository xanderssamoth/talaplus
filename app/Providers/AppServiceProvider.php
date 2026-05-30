<?php

namespace App\Providers;

use App\Helpers\ApiStoreColumnsExtension;
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
        //
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
