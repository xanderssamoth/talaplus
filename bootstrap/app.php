<?php

use App\Http\Controllers\BaseController;
use App\Http\Middleware\Localization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

if (! function_exists('apiExceptionEntity')) {
    function apiExceptionEntity(Request $request, ?string $model = null): string
    {
        if ($model !== null && $model !== '') {
            return Str::snake(class_basename($model));
        }

        return Str::of((string) $request->segment(3))->replace('-', '_')->toString();
    }
}

if (! function_exists('apiNotFoundMessage')) {
    function apiNotFoundMessage(string $entity): string
    {
        $message = __("api.entities.{$entity}.not_found");

        return $message === "api.entities.{$entity}.not_found"
            ? __('api.errors.not_found')
            : $message;
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            Localization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $entity = apiExceptionEntity($request, $exception->getModel());

            return app(BaseController::class)->handleError(null, apiNotFoundMessage($entity), 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $exception->getPrevious();
            $entity = $previous instanceof ModelNotFoundException
                ? apiExceptionEntity($request, $previous->getModel())
                : apiExceptionEntity($request);

            return app(BaseController::class)->handleError(null, apiNotFoundMessage($entity), 404);
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(BaseController::class)->handleError(null, __('api.errors.query_failed'), 500);
        });
    })->create();
