<?php

namespace App\Console\Commands;

use App\Services\BiometricLogCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunBiometricAutoCollectCommand extends Command
{
    protected $signature = 'biometric:auto-collect';

    protected $description = 'Background worker: pull device logs on a fixed interval (NativePHP desktop)';

    public function handle(BiometricLogCollector $collector): int
    {
        $intervalMinutes = max(1, (int) config('biometric.collection_interval_minutes', 5));
        $intervalSeconds = $intervalMinutes * 60;

        $this->info(sprintf('Auto-collect started (every %d minute(s)).', $intervalMinutes));

        while (true) {
            try {
                $summary = $collector->run();

                Log::info('biometric:auto-collect finished', [
                    'devices_processed' => $summary['devices_processed'],
                    'logs_inserted' => $summary['logs_inserted'],
                    'batches_uploaded' => $summary['batches_uploaded'],
                    'errors' => $summary['errors'],
                ]);
            } catch (\Throwable $exception) {
                report($exception);
                Log::error('biometric:auto-collect failed', [
                    'message' => $exception->getMessage(),
                ]);
            }

            sleep($intervalSeconds);
        }
    }
}
