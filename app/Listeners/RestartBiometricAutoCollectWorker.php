<?php

namespace App\Listeners;

use App\Support\BiometricAutoCollectWorker;
use App\Support\CollectRunTracker;
use App\Support\ManualCollectWorker;
use Native\Laravel\Events\ChildProcess\ProcessExited;

class RestartBiometricAutoCollectWorker
{
    public function handle(ProcessExited $event): void
    {
        if ($event->alias === ManualCollectWorker::ALIAS) {
            if ($event->code !== 0) {
                $tracker = app(CollectRunTracker::class);

                if ($tracker->isRunning()) {
                    $tracker->markFailed(
                        'Background collect stopped unexpectedly (exit '.$event->code.'). Try Collect now again.',
                    );
                }
            }

            return;
        }

        if ($event->alias !== BiometricAutoCollectWorker::ALIAS) {
            return;
        }

        BiometricAutoCollectWorker::ensureStarted();
    }
}
