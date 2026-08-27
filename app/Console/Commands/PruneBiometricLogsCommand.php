<?php

namespace App\Console\Commands;

use App\Services\BiometricLogRetentionService;
use Illuminate\Console\Command;
use Throwable;

class PruneBiometricLogsCommand extends Command
{
    protected $signature = 'biometric:prune-logs';

    protected $description = 'Archive collected logs older than the dashboard retention window to local JSON, then delete them';

    public function handle(BiometricLogRetentionService $retention): int
    {
        try {
            $summary = $retention->pruneExpiredLogs();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($summary['skipped']) {
            $this->info('No retention months set. Collected logs were left unchanged.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Archived and deleted %d log(s) older than %s into %s.',
            $summary['pruned'],
            $summary['cutoff_at'] ?? 'the retention cutoff',
            $retention->archiveDirectory(),
        ));

        foreach ($summary['files'] as $file) {
            $this->line('  '.$file);
        }

        return self::SUCCESS;
    }
}
