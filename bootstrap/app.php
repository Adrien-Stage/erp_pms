<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Vérifie chaque minute les planifications de backup échues.
        // Nécessite un scheduler actif : `php artisan schedule:work` (dev)
        // ou une entrée cron `* * * * * php artisan schedule:run` (prod).
        $schedule->command('backups:run')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\TrackUserOnlineStatus::class,
        ]);

        // RBAC Middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRoleAccess::class,
            'admin' => \App\Http\Middleware\AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
