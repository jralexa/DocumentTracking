<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

if (getenv('APP_CONFIG_CACHE') === false) {
    $configCachePath = 'storage/framework/cache/config.php';
    putenv('APP_CONFIG_CACHE='.$configCachePath);
    $_ENV['APP_CONFIG_CACHE'] = $configCachePath;
    $_SERVER['APP_CONFIG_CACHE'] = $configCachePath;
}

if (getenv('APP_SERVICES_CACHE') === false) {
    $servicesCachePath = 'storage/framework/cache/services.php';
    putenv('APP_SERVICES_CACHE='.$servicesCachePath);
    $_ENV['APP_SERVICES_CACHE'] = $servicesCachePath;
    $_SERVER['APP_SERVICES_CACHE'] = $servicesCachePath;
}

if (getenv('APP_PACKAGES_CACHE') === false) {
    $packagesCachePath = 'storage/framework/cache/packages.php';
    putenv('APP_PACKAGES_CACHE='.$packagesCachePath);
    $_ENV['APP_PACKAGES_CACHE'] = $packagesCachePath;
    $_SERVER['APP_PACKAGES_CACHE'] = $packagesCachePath;
}

if (PHP_SAPI === 'cli' && empty($_SERVER['PSYSH_CONFIG'])) {
    $psyshConfigFile = dirname(__DIR__).'/psysh.config.php';

    if (is_file($psyshConfigFile)) {
        putenv('PSYSH_CONFIG='.$psyshConfigFile);
        $_ENV['PSYSH_CONFIG'] = $psyshConfigFile;
        $_SERVER['PSYSH_CONFIG'] = $psyshConfigFile;
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
