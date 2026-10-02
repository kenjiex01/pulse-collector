<?php

namespace App\Providers;

use App\Services\DesktopUpdaterService;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()
            ->title(config('app.name'))
            ->url(route('dashboard'))
            ->width(1200)
            ->height(760)
            ->minWidth(960)
            ->minHeight(600);

        try {
            app(DesktopUpdaterService::class)->requestBackgroundCheck();
        } catch (\Throwable) {
            // Ignore updater failures so the dashboard still loads.
        }
    }

    public function phpIni(): array
    {
        return [
            'memory_limit' => '1024M',
            'max_execution_time' => '0',
        ];
    }
}
