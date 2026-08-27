<?php

namespace App\Providers;

use App\Console\Commands\CollectBiometricLogsCommand;
use App\Console\Commands\EncryptEnvSecretsCommand;
use App\Console\Commands\RunBiometricAutoCollectCommand;
use App\Listeners\HandleDesktopUpdaterEvents;
use App\Listeners\StopLegacyBiometricAutoCollectWorker;
use App\Services\DesktopUpdaterService;
use App\Support\EncryptedEnv;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Native\Laravel\Events\App\ApplicationBooted;
use Native\Laravel\Events\AutoUpdater\DownloadProgress;
use Native\Laravel\Events\AutoUpdater\Error;
use Native\Laravel\Events\AutoUpdater\UpdateAvailable;
use Native\Laravel\Events\AutoUpdater\UpdateDownloaded;
use Native\Laravel\Events\AutoUpdater\UpdateNotAvailable;

class AppServiceProvider extends ServiceProvider
{
    private static bool $desktopDatabaseEnsured = false;

    public function register(): void
    {
        $this->app->singleton(DesktopUpdaterService::class);

        $this->commands([
            CollectBiometricLogsCommand::class,
            RunBiometricAutoCollectCommand::class,
            EncryptEnvSecretsCommand::class,
        ]);
    }

    public function boot(): void
    {
        // Decrypt S3 secrets into memory before uploads / installer checks run.
        EncryptedEnv::revealConfiguredSecrets();

        $this->registerDesktopUpdater();

        View::composer(['collector.layout', 'layouts.app', 'layouts.guest'], function ($view): void {
            try {
                $updater = app(DesktopUpdaterService::class)->status();
            } catch (\Throwable) {
                $updater = [
                    'enabled' => false,
                    'force_install' => false,
                    'version' => (string) config('nativephp.version', '0.0.0'),
                    'pending' => null,
                    'downloading' => null,
                    'installing' => null,
                ];
            }

            $view->with('desktopUpdater', $updater);
        });

        Event::listen(ApplicationBooted::class, StopLegacyBiometricAutoCollectWorker::class);

        if ($this->app->runningInConsole() && ! $this->isNativeDesktop()) {
            return;
        }

        $this->ensureDesktopDatabase();
    }

    private function registerDesktopUpdater(): void
    {
        $listener = HandleDesktopUpdaterEvents::class;

        Event::listen(UpdateAvailable::class, [$listener, 'handleUpdateAvailable']);
        Event::listen(DownloadProgress::class, [$listener, 'handleDownloadProgress']);
        Event::listen(UpdateDownloaded::class, [$listener, 'handleUpdateDownloaded']);
        Event::listen(UpdateNotAvailable::class, [$listener, 'handleUpdateNotAvailable']);
        Event::listen(Error::class, [$listener, 'handleError']);
    }

    private function isNativeDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    private function ensureDesktopDatabase(): void
    {
        if (! $this->isNativeDesktop() || self::$desktopDatabaseEnsured) {
            return;
        }

        self::$desktopDatabaseEnsured = true;

        $databasePath = storage_path('app/biometric-collector.sqlite');
        $isFirstLaunch = ! File::exists($databasePath) || File::size($databasePath) === 0;

        if ($isFirstLaunch) {
            File::ensureDirectoryExists(dirname($databasePath));
            File::put($databasePath, '');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->ensureLogRetentionColumn();

            if ($isFirstLaunch || ! \App\Models\Campus::query()->exists()) {
                Artisan::call('db:seed', ['--force' => true]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function ensureLogRetentionColumn(): void
    {
        if (! Schema::hasTable('collector_installations')) {
            return;
        }

        if (Schema::hasColumn('collector_installations', 'log_retention_months')) {
            return;
        }

        Schema::table('collector_installations', function (Blueprint $table) {
            $table->unsignedSmallInteger('log_retention_months')->nullable();
        });
    }
}
