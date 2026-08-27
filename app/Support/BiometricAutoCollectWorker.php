<?php

namespace App\Support;

use Native\Laravel\Facades\ChildProcess;

class BiometricAutoCollectWorker
{
    public const ALIAS = 'biometric_auto_collect';

    public static function ensureStarted(): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        if (ChildProcess::get(self::ALIAS) !== null) {
            return;
        }

        $root = str_replace('\\', '/', (string) realpath(base_path() ?: '') ?: base_path());

        ChildProcess::php(
            [$root.'/artisan', 'biometric:auto-collect'],
            self::ALIAS,
            env: [
                'APP_PATH' => $root,
            ],
            persistent: true,
            iniSettings: [
                'memory_limit' => '1024M',
                'max_execution_time' => '0',
            ],
        );
    }
}
