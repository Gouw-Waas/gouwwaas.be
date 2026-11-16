<?php

use Dotenv\Dotenv;
use Dotenv\Repository\RepositoryBuilder;
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
        // Mollie can't send a CSRF token; the webhook only takes a payment id and asks Mollie itself.
        $middleware->validateCsrfTokens(except: ['mollie/webhook']);
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

// Secrets for a committed env file live next to it in a git-ignored "<file>.secrets"
// (e.g. .env.local.secrets with MOLLIE_API_KEY) and win over the values in the file.
$app->afterLoadingEnvironment(function (Application $app) {
    $secrets = $app->environmentFile().'.secrets';

    if (is_file($app->environmentPath().DIRECTORY_SEPARATOR.$secrets)) {
        Dotenv::create(RepositoryBuilder::createWithDefaultAdapters()->make(), $app->environmentPath(), $secrets)->load();
    }
});

return $app;
