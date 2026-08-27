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
        return static::query()->firstOrCreate([], ['name' => null]);
    }
}
