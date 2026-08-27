<?php

namespace App\Listeners;

use App\Support\BiometricAutoCollectWorker;
use Native\Laravel\Events\App\ApplicationBooted;

class StartBiometricScheduleWorker
{
    public function handle(ApplicationBooted $event): void
    {
        BiometricAutoCollectWorker::ensureStarted();
    }
}
