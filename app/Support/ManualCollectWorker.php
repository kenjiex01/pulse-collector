<?php

namespace App\Support;

use Native\Laravel\Facades\ChildProcess;
use Throwable;

class ManualCollectWorker
{
    public const ALIAS = 'biometric_manual_collect';

    public function __construct(
        private readonly CollectRunTracker $tracker,
    ) {}

    public function isNativeDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    public function isChildRunning(): bool
    {
        if (! $this->isNativeDesktop()) {
            return false;
        }

        try {
            return ChildProcess::get(self::ALIAS) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{started: bool, message: string}
     */
    public function start(): array
    {
        if ($this->isChildRunning() || $this->tracker->isRunning()) {
            return [
                'started' => true,
                'message' => 'Collection is already running. Please wait…',
            ];
        }

        $this->tracker->markRunning();

        $root = str_replace('\\', '/', (string) realpath(base_path() ?: '') ?: base_path());

        try {
            ChildProcess::php(
                [$root.'/artisan', 'biometric:collect', '--json'],
                self::ALIAS,
                env: [
                    'APP_PATH' => $root,
                    'BIOMETRIC_COLLECT_IN_PROCESS' => '1',
                ],
                persistent: false,
                iniSettings: [
                    'memory_limit' => '1024M',
                    'max_execution_time' => '0',
                ],
            );
        } catch (Throwable $exception) {
            $this->tracker->markFailed(
                'Could not start background collect: '.$exception->getMessage(),
            );

            throw $exception;
        }

        return [
            'started' => true,
            'message' => 'Collection started in the background. The dashboard will stay responsive.',
        ];
    }
}
