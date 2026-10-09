<?php

namespace Tests\Feature;

use App\Models\CollectorInstallation;
use App\Services\BiometricLogCollector;
use App\Support\CollectRunTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BiometricCollectNowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/collect-run.json'));
        parent::tearDown();
    }

    public function test_new_installation_defaults_log_retention_to_two_months(): void
    {
        $installation = CollectorInstallation::current();

        $this->assertSame(2, $installation->log_retention_months);
    }

    public function test_collect_now_returns_json_instead_of_server_error_when_collect_throws(): void
    {
        CollectorInstallation::current()->update([
            'log_retention_months' => 3,
        ]);

        $this->mock(BiometricLogCollector::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andThrow(new \RuntimeException('S3 upload failed'));
        });

        $response = $this->postJson(route('collect.now'));

        $response->assertOk()
            ->assertJson([
                'ok' => false,
                'started' => false,
                'message' => 'S3 upload failed',
            ]);

        $this->assertSame(
            CollectRunTracker::STATE_FAILED,
            app(CollectRunTracker::class)->current()['state'],
        );
    }

    public function test_collect_status_reports_idle_by_default(): void
    {
        File::delete(storage_path('app/collect-run.json'));

        $this->getJson(route('collect.status'))
            ->assertOk()
            ->assertJson([
                'state' => CollectRunTracker::STATE_IDLE,
                'done' => false,
                'started' => false,
            ]);
    }

    public function test_attendance_start_date_can_be_saved_from_dashboard(): void
    {
        $response = $this->post(route('settings.attendance-start-date'), [
            'attendance_start_date' => '2026-08-17',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(
            '2026-08-17',
            CollectorInstallation::current()->attendance_start_date?->format('Y-m-d'),
        );
    }

    public function test_collect_now_is_blocked_when_retention_months_are_not_set(): void
    {
        CollectorInstallation::current()->update(['log_retention_months' => null]);

        $response = $this->postJson(route('collect.now'));

        $response->assertOk()
            ->assertJson([
                'ok' => false,
                'started' => false,
                'message' => 'Set Months to keep first before collecting logs. Save a retention month value so the app can archive old logs safely.',
            ]);
    }

    public function test_auto_collect_is_blocked_when_retention_months_are_not_set(): void
    {
        CollectorInstallation::current()->update(['log_retention_months' => null]);

        $response = $this->postJson(route('collect.auto'));

        $response->assertOk()
            ->assertJson([
                'ok' => false,
                'message' => 'Set Months to keep first before collecting logs. This prevents the app from keeping or deleting your full history by mistake.',
            ]);
    }
}
