<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDeviceUser extends Model
{
    protected $fillable = [
        'biometric_device_id',
        'device_uid',
        'user_id',
        'name',
        'card_number',
        'privilege',
        'synced_at',
        's3_export_batch_id',
        's3_pushed_at',
        's3_object_key',
    ];

    protected function casts(): array
    {
        return [
            'device_uid' => 'integer',
            'synced_at' => 'datetime',
            's3_pushed_at' => 'datetime',
        ];
    }

    public function scopePendingS3Push(Builder $query): Builder
    {
        return $query->whereNull('s3_pushed_at');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }
}
