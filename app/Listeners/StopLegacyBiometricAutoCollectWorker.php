<?php

namespace App\Listeners;

use App\Support\BiometricAutoCollectWorker;
use Native\Laravel\Events\App\ApplicationBooted;
use Native\Laravel\Facades\ChildProcess;

class StopLegacyBiometricAutoCollectWorker
{
    public function handle(ApplicationBooted $event): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        try {
            ChildProcess::stop(BiometricAutoCollectWorker::ALIAS);
        } catch (\Throwable) {
            //
        }
    }
}
