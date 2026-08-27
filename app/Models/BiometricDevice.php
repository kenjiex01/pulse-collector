<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends Model
{
    protected $fillable = [
        'campus_id',
        'name',
        'model',
        'serial_number',
        'ip_address',
        'port',
        'comm_key',
        'is_active',
        'last_collected_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_collected_at' => 'datetime',
            'port' => 'integer',
            'comm_key' => 'integer',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(BiometricAttendanceLog::class);
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(BiometricDeviceUser::class);
    }
}
