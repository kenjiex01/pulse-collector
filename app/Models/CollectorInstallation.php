<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorInstallation extends Model
{
    protected $fillable = [
        'name',
        'attendance_start_date',
        'log_retention_months',
    ];

    protected function casts(): array
    {
        return [
            'attendance_start_date' => 'date',
            'log_retention_months' => 'integer',
        ];
    }

    public static function current(): self
    {
        $defaultMonths = max(1, (int) config('biometric.logs.retention.default_months', 2));

        return static::query()->firstOrCreate([], [
            'name' => null,
            'log_retention_months' => $defaultMonths,
        ]);
    }
}
