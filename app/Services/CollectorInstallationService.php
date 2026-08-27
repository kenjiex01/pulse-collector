<?php

namespace App\Services;

use App\Models\CollectorInstallation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CollectorInstallationService
{
    public function displayName(): ?string
    {
        $fromDb = CollectorInstallation::current()->name;

        if (filled($fromDb)) {
            return trim($fromDb);
        }

        $fromEnv = config('biometric.collector.name');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    /**
     * Slug used in S3 keys and export filenames (sanitized).
     */
    public function slug(): string
    {
        $name = $this->displayName();

        if ($name !== null && $name !== '') {
            $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
            $slug = trim($slug, '.-_');

            if ($slug !== '') {
                return substr($slug, 0, 64);
            }
        }

        $host = gethostname() ?: php_uname('n') ?: 'desktop';
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $host) ?? 'desktop';
        $slug = trim($slug, '.-_');

        return $slug === '' ? 'desktop' : substr($slug, 0, 64);
    }

    public function updateName(string $name): CollectorInstallation
    {
        $name = trim($name);
        $installation = CollectorInstallation::current();
        $installation->fill(['name' => $name === '' ? null : $name]);
        $installation->save();

        return $installation;
    }

    public function attendanceStartDate(): ?CarbonImmutable
    {
        $value = CollectorInstallation::current()->attendance_start_date;

        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }

    public function updateAttendanceStartDate(?string $date): CollectorInstallation
    {
        $installation = CollectorInstallation::current();
        $installation->fill([
            'attendance_start_date' => filled($date) ? CarbonImmutable::parse($date)->toDateString() : null,
        ]);
        $installation->save();

        return $installation;
    }

    public function logRetentionMonths(): ?int
    {
        $value = CollectorInstallation::current()->log_retention_months;

        if ($value === null) {
            return null;
        }

        $months = (int) $value;

        return $months > 0 ? $months : null;
    }

    public function updateLogRetentionMonths(?int $months): CollectorInstallation
    {
        $this->ensureLogRetentionColumn();

        $installation = CollectorInstallation::current();
        $installation->fill([
            'log_retention_months' => $months !== null && $months > 0 ? $months : null,
        ]);
        $installation->save();

        return $installation;
    }

    public function ensureLogRetentionColumn(): void
    {
        if (! Schema::hasTable('collector_installations')) {
            return;
        }

        if (Schema::hasColumn('collector_installations', 'log_retention_months')) {
            return;
        }

        Schema::table('collector_installations', function (Blueprint $table) {
            $table->unsignedSmallInteger('log_retention_months')->nullable();
        });
    }
}
