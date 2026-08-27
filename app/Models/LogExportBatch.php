<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogExportBatch extends Model
{
    protected $fillable = [
        'campus_id',
        'biometric_device_id',
        'batch_kind',
        'logs_count',
        'users_count',
        'sql_filename',
        's3_key',
        'uploaded_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'logs_count' => 'integer',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BiometricAttendanceLog::class, 'log_export_batch_id');
    }
}
