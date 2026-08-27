<?php

namespace Tests\Unit;

use App\Support\CollectRunTracker;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CollectRunTrackerTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(storage_path('app/collect-run.json'));
        parent::tearDown();
    }

    public function test_starts_idle_when_no_status_file_exists(): void
    {
        File::delete(storage_path('app/collect-run.json'));

        $status = app(CollectRunTracker::class)->current();

        $this->assertSame(CollectRunTracker::STATE_IDLE, $status['state']);
    }

    public function test_mark_finished_records_summary_and_message(): void
    {
        $tracker = app(CollectRunTracker::class);
        $tracker->markRunning('Collecting…');
        $tracker->markFinished([
            'devices_processed' => 1,
            'logs_inserted' => 12,
            'batches_uploaded' => 1,
            'errors' => [],
        ]);

        $status = $tracker->current();

        $this->assertSame(CollectRunTracker::STATE_FINISHED, $status['state']);
        $this->assertSame('Collection finished: 12 new log(s), 1 S3 upload(s).', $status['message']);
        $this->assertSame(12, $status['summary']['logs_inserted']);
    }

    public function test_stale_running_status_is_marked_failed(): void
    {
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/collect-run.json'), json_encode([
            'state' => CollectRunTracker::STATE_RUNNING,
            'message' => 'Collecting…',
            'started_at' => now()->subMinutes(20)->toIso8601String(),
            'finished_at' => null,
            'summary' => null,
        ]));

        $status = app(CollectRunTracker::class)->current();

        $this->assertSame(CollectRunTracker::STATE_FAILED, $status['state']);
        $this->assertStringContainsString('timed out', $status['message']);
    }
}
