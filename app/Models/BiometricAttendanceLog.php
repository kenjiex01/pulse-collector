<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAttendanceLog extends Model
{
    protected $fillable = [
        'campus_id',
        'biometric_device_id',
        'device_uid',
        'user_id',
        'user_name',
        'card_number',
        'punched_at',
        'verify_mode',
        'punch_state',
        'log_export_batch_id',
        's3_pushed_at',
        's3_object_key',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'device_uid' => 'integer',
            's3_pushed_at' => 'datetime',
        ];
    }

    public function scopePendingS3Push(Builder $query): Builder
    {
        return $query->whereNull('s3_pushed_at');
    }

    public function isPushedToS3(): bool
    {
        return $this->s3_pushed_at !== null;
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function exportBatch(): BelongsTo
    {
        return $this->belongsTo(LogExportBatch::class, 'log_export_batch_id');
    }
}
