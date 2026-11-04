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

    /**
     * @return array{address: string, contact_number: string, email: string, website: string}
     */
    public function dtrLetterhead(): array
    {
        $this->ensureDtrLetterheadColumns();

        $installation = CollectorInstallation::current();

        return [
            'address' => $this->letterheadValue($installation->dtr_address),
            'contact_number' => $this->letterheadValue($installation->dtr_contact_number),
            'email' => $this->letterheadValue($installation->dtr_email),
            'website' => $this->letterheadValue($installation->dtr_website),
        ];
    }

    /**
     * @return list<string>
     */
    public function blankDtrLetterheadLabels(): array
    {
        $letterhead = $this->dtrLetterhead();
        $labels = [
            'address' => 'Address',
            'contact_number' => 'Contact number',
            'email' => 'Email address',
            'website' => 'Website',
        ];
        $blank = [];

        foreach ($labels as $key => $label) {
            if ($letterhead[$key] === '') {
                $blank[] = $label;
            }
        }

        return $blank;
    }

    public function dtrContactLine(): string
    {
        $letterhead = $this->dtrLetterhead();
        $parts = [];

        if ($letterhead['contact_number'] !== '') {
            $parts[] = 'Telephone: '.$letterhead['contact_number'];
        }

        if ($letterhead['website'] !== '') {
            $parts[] = 'Website: '.$letterhead['website'];
        }

        if ($letterhead['email'] !== '') {
            $parts[] = 'Email: '.$letterhead['email'];
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{address?: string|null, contact_number?: string|null, email?: string|null, website?: string|null}  $values
     */
    public function updateDtrLetterhead(array $values): CollectorInstallation
    {
        $this->ensureDtrLetterheadColumns();

        $installation = CollectorInstallation::current();
        $installation->fill([
            'dtr_address' => $this->storedLetterhead($values['address'] ?? null),
            'dtr_contact_number' => $this->storedLetterhead($values['contact_number'] ?? null),
            'dtr_email' => $this->storedLetterhead($values['email'] ?? null),
            'dtr_website' => $this->storedLetterhead($values['website'] ?? null),
        ]);
        $installation->save();

        return $installation;
    }

    public function ensureDtrLetterheadColumns(): void
    {
        if (! Schema::hasTable('collector_installations')) {
            return;
        }

        $missing = array_filter(
            ['dtr_address', 'dtr_contact_number', 'dtr_email', 'dtr_website'],
            fn (string $column): bool => ! Schema::hasColumn('collector_installations', $column),
        );

        if ($missing === []) {
            return;
        }

        Schema::table('collector_installations', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    private function letterheadValue(mixed $stored): string
    {
        return trim((string) $stored);
    }

    private function storedLetterhead(mixed $value): string
    {
        return trim((string) $value);
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
