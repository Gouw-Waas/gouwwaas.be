<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Local development has no .env: use the committed .env.local. APP_ENV=prod makes
// Laravel load .env.prod instead (.env + "." + APP_ENV). Servers keep using their .env.
if (! is_file($app->basePath('.env')) && ! Env::get('APP_ENV')) {
    $app->loadEnvironmentFrom('.env.local');
}

return $app;
