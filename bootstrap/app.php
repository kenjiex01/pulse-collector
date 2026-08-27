<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        if (config('nativephp-internal.running')) {
            return;
        }

        $interval = max(1, (int) config('biometric.collection_interval_minutes', 2));

        $schedule->command('biometric:collect')
            ->cron('*/'.$interval.' * * * *')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureDesktopInstallerUpdate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e): bool {
            return $request->expectsJson()
                || $request->wantsJson()
                || $request->ajax()
                || $request->is('collect', 'collect/*');
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            if (! $request->isMethod('POST') || ! $request->is('settings/log-retention')) {
                return null;
            }

            report($e);

            $message = trim($e->getMessage());
            if ($message === '' || strcasecmp($message, 'Server Error') === 0) {
                $message = 'Could not save Months to keep. Restart the app and try again.';
            }

            return redirect()
                ->route('dashboard')
                ->with('warning', $message);
        });
    })->create();
