<?php

namespace App\Console\Commands;

use App\Services\DesktopUpdaterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckDesktopUpdatesCommand extends Command
{
    protected $signature = 'desktop:check-updates';

    protected $description = 'Check for NativePHP app updates without blocking the web server';

    public function handle(DesktopUpdaterService $updater): int
    {
        try {
            $updater->checkForUpdates();
        } finally {
            Cache::forget(DesktopUpdaterService::CACHE_CHECK_IN_PROGRESS);
        }

        return self::SUCCESS;
    }
}
