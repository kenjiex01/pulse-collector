<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\LogExportBatch;
use App\Services\BiometricDeviceReachabilityService;
use App\Services\BiometricLogCollector;
use App\Services\BiometricLogRetentionService;
use App\Services\CollectorInstallationService;
use App\Support\CollectRunTracker;
use App\Support\ManualCollectWorker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CollectorDashboardController extends Controller
{
    public function index(): View
    {
        $devices = BiometricDevice::query()
            ->with('campus')
            ->orderBy('campus_id')
            ->orderBy('name')
            ->get();

        $recentExports = LogExportBatch::query()
            ->with(['campus', 'device'])
            ->latest('id')
            ->limit(20)
            ->get();

        $deviceStatuses = app(BiometricDeviceReachabilityService::class)->checkMany($devices);

        $collectIntervalMinutes = max(1, (int) config('biometric.collection_interval_minutes', 5));

        return view('collector.dashboard', [
            'devices' => $devices,
            'deviceStatuses' => $deviceStatuses,
            'recentExports' => $recentExports,
            's3Configured' => app(\App\Services\BiometricLogS3Uploader::class)->isConfigured(),
            'collectIntervalMinutes' => $collectIntervalMinutes,
            'collectCronExpression' => '*/'.$collectIntervalMinutes.' * * * *',
            'statusPollSeconds' => (int) config('biometric.device.status_poll_seconds', 10),
            'collectorName' => app(CollectorInstallationService::class)->displayName(),
            'collectorSlug' => app(CollectorInstallationService::class)->slug(),
            'attendanceStartDate' => app(CollectorInstallationService::class)
                ->attendanceStartDate()?->toDateString(),
            'logRetentionMonths' => app(CollectorInstallationService::class)->logRetentionMonths(),
            'logRetentionArchiveDir' => app(BiometricLogRetentionService::class)->archiveDirectoryPath(),
            'logRetentionMinMonths' => (int) config('biometric.logs.retention.min_months', 1),
            'logRetentionMaxMonths' => (int) config('biometric.logs.retention.max_months', 120),
        ]);
    }

    public function updateCollectorName(Request $request, CollectorInstallationService $installation): RedirectResponse
    {
        $validated = $request->validate([
            'collector_name' => ['required', 'string', 'max:128'],
        ]);

        $installation->updateName($validated['collector_name']);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Collector name saved. Future S3 uploads will use this label.');
    }

    public function updateAttendanceStartDate(
        Request $request,
        CollectorInstallationService $installation,
    ): RedirectResponse {
        $validated = $request->validate([
            'attendance_start_date' => ['nullable', 'date'],
        ]);

        $installation->updateAttendanceStartDate($validated['attendance_start_date'] ?? null);

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                filled($validated['attendance_start_date'] ?? null)
                    ? 'Attendance start date saved. New collections will ignore older punches.'
                    : 'Attendance start date cleared. Collections will include all device punches again.',
            );
    }

    public function updateLogRetention(
        Request $request,
        CollectorInstallationService $installation,
        BiometricLogRetentionService $retention,
    ): RedirectResponse {
        $minMonths = (int) config('biometric.logs.retention.min_months', 1);
        $maxMonths = (int) config('biometric.logs.retention.max_months', 120);

        $validated = $request->validate([
            'log_retention_months' => ['nullable', 'integer', 'min:'.$minMonths, 'max:'.$maxMonths],
        ]);

        $months = isset($validated['log_retention_months']) && $validated['log_retention_months'] !== null
            ? (int) $validated['log_retention_months']
            : null;

        try {
            $installation->updateLogRetentionMonths($months);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('dashboard')
                ->with(
                    'warning',
                    'Could not save Months to keep: '.$this->friendlyRetentionError($exception->getMessage()),
                );
        }

        if ($months === null) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Log retention cleared. Collect now and auto-collect stay blocked until you set a month window.');
        }

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                'Log retention saved. Keep punches from the last '.$months.' month(s). '
                .'Older logs will be archived to JSON in '.$retention->archiveDirectoryName()
                .' and removed on the next Collect now / auto-collect — not while saving.',
            );
    }

    public function collectNow(
        Request $request,
        BiometricLogCollector $collector,
        CollectRunTracker $tracker,
        ManualCollectWorker $manualCollect,
        CollectorInstallationService $installation,
    ): RedirectResponse|JsonResponse {
        try {
            $this->ensureLogRetentionConfigured($installation);

            if ($manualCollect->isNativeDesktop()) {
                $started = $manualCollect->start();

                if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'ok' => true,
                        'started' => true,
                        'message' => $started['message'],
                    ]);
                }

                return redirect()
                    ->route('dashboard')
                    ->with('success', $started['message']);
            }

            $summary = $collector->run();
            $tracker->markFinished($summary);

            return $this->collectFinishedResponse($request, $summary);
        } catch (Throwable $exception) {
            report($exception);
            $tracker->markFailed($exception->getMessage());

            return $this->collectFailedResponse($request, $exception->getMessage());
        }
    }

    public function collectStatus(CollectRunTracker $tracker): JsonResponse
    {
        $status = $tracker->current();

        return response()->json([
            'ok' => $status['state'] !== CollectRunTracker::STATE_FAILED
                && ($status['summary']['errors'] ?? []) === [],
            'started' => $status['state'] === CollectRunTracker::STATE_RUNNING,
            'done' => in_array($status['state'], [
                CollectRunTracker::STATE_FINISHED,
                CollectRunTracker::STATE_FAILED,
            ], true),
            'state' => $status['state'],
            'message' => $status['message'],
            'summary' => $status['summary'],
        ]);
    }

    public function collectAuto(BiometricLogCollector $collector, CollectRunTracker $tracker): JsonResponse
    {
        try {
            $summary = $collector->run();
            $tracker->markFinished($summary);

            return response()->json([
                'ok' => $summary['errors'] === [],
                'summary' => $summary,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $tracker->markFailed($exception->getMessage());

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Automatic collection failed.',
                'summary' => [
                    'devices_processed' => 0,
                    'logs_inserted' => 0,
                    'batches_uploaded' => 0,
                    'errors' => [$exception->getMessage()],
                ],
            ]);
        }
    }

    /**
     * @param  array{
     *     devices_processed: int,
     *     logs_inserted: int,
     *     batches_uploaded: int,
     *     errors: list<string>,
     * }  $summary
     */
    private function collectFinishedResponse(Request $request, array $summary): RedirectResponse|JsonResponse
    {
        $message = sprintf(
            'Collection finished: %d new log(s), %d S3 upload(s).',
            $summary['logs_inserted'],
            $summary['batches_uploaded'],
        );

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => $summary['errors'] === [],
                'started' => false,
                'message' => $summary['errors'] === []
                    ? $message
                    : $message.' Some devices failed — see the Last collect error column.',
                'summary' => $summary,
            ]);
        }

        if ($summary['errors'] !== []) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'warning',
                    $message.' Some devices failed — see the Last collect error column below.',
                );
        }

        return redirect()
            ->route('dashboard')
            ->with('success', $message);
    }

    private function collectFailedResponse(Request $request, string $error): RedirectResponse|JsonResponse
    {
        $message = $this->friendlyCollectError($error);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'started' => false,
                'message' => $message,
                'summary' => [
                    'devices_processed' => 0,
                    'logs_inserted' => 0,
                    'batches_uploaded' => 0,
                    'errors' => [$message],
                ],
            ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('warning', $message);
    }

    private function friendlyCollectError(string $error): string
    {
        $trimmed = trim($error);

        if ($trimmed === '' || strcasecmp($trimmed, 'Server Error') === 0) {
            return 'Collection failed on this desktop. Check Last collect error, then try Collect now again.';
        }

        if (str_contains(strtolower($trimmed), 'allowed memory size')) {
            return 'Collection ran out of memory while reading device logs. Close other apps and try Collect now again.';
        }

        return $trimmed;
    }

    private function friendlyRetentionError(string $error): string
    {
        $trimmed = trim($error);

        if (str_contains(strtolower($trimmed), 'no such column')
            || str_contains(strtolower($trimmed), 'has no column')) {
            return 'The app database is missing the retention column. Close the app and reopen it so migrations can run, then try Save retention again.';
        }

        if ($trimmed === '' || strcasecmp($trimmed, 'Server Error') === 0) {
            return 'Save failed on this desktop. Try again, or restart the app.';
        }

        return $trimmed;
    }

    private function ensureLogRetentionConfigured(CollectorInstallationService $installation): void
    {
        if ($installation->logRetentionMonths() !== null) {
            return;
        }

        throw new \RuntimeException(
            'Set Months to keep first before collecting logs. Save a retention month value so the app can archive old logs safely.',
        );
    }
}
