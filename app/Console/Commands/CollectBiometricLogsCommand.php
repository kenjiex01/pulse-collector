<?php

namespace App\Console\Commands;

use App\Services\BiometricLogCollector;
use App\Support\CollectRunTracker;
use Illuminate\Console\Command;
use Throwable;

class CollectBiometricLogsCommand extends Command
{
    protected $signature = 'biometric:collect {--json : Output run summary as JSON}';

    protected $description = 'Pull attendance logs from configured ZkTeco devices and upload gzipped JSON to S3 when new logs exist';

    public function handle(BiometricLogCollector $collector, CollectRunTracker $tracker): int
    {
        $tracker->markRunning();

        try {
            $summary = $collector->run();
            $tracker->markFinished($summary);
        } catch (Throwable $exception) {
            $tracker->markFailed($exception->getMessage());

            if ($this->option('json')) {
                $this->line(json_encode([
                    'devices_processed' => 0,
                    'logs_inserted' => 0,
                    'batches_uploaded' => 0,
                    'errors' => [$exception->getMessage()],
                ], JSON_THROW_ON_ERROR));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR));

            return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf(
            'Processed %d device(s), inserted %d new log(s), uploaded %d batch(es).',
            $summary['devices_processed'],
            $summary['logs_inserted'],
            $summary['batches_uploaded'],
        ));

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
