<?php

use App\Http\Middleware\CorsForExtensions;
use Throwable;
use App\Http\Middleware\VerifyCheckoutExtensionSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('api')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'checkout.extension.signature' => VerifyCheckoutExtensionSignature::class,
            'cors.extensions' => CorsForExtensions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e): void {
            if (app()->environment('production')) {
                $msg = sprintf(
                    "[%s] %s in %s:%d\n%s",
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                );
                fwrite(STDERR, $msg);
            }
        });
    })->create();
