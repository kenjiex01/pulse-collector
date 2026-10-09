<?php

return [

    'collection_interval_minutes' => (int) env('BIOMETRIC_COLLECT_INTERVAL_MINUTES', 5),

    /*
    | Optional fallback when no name is set in the desktop app (SQLite).
    | Prefer setting the name on the dashboard — it is included in S3 paths.
    */
    'collector' => [
        'name' => env('BIOMETRIC_COLLECTOR_NAME'),
    ],

    'device' => [
        'default_port' => (int) env('BIOMETRIC_DEVICE_PORT', 4370),
        'timeout_seconds' => (float) env('BIOMETRIC_DEVICE_TIMEOUT', 10),
        'enroll_timeout_seconds' => (float) env('BIOMETRIC_DEVICE_ENROLL_TIMEOUT', 90),
        'reachability_timeout_seconds' => (float) env('BIOMETRIC_DEVICE_REACHABILITY_TIMEOUT', 5),
        'status_poll_seconds' => (int) env('BIOMETRIC_STATUS_POLL_SECONDS', 10),
        'default_model' => env('BIOMETRIC_DEVICE_MODEL', 'K30'),
    ],

    /*
    | SQLite timestamps for exports/sync were written under UTC before APP_TIMEZONE
    | defaulted to Asia/Manila. Keep true until you migrate or re-export history.
    */
    'timestamps_stored_as_utc' => (bool) env('BIOMETRIC_TIMESTAMPS_UTC', true),

    'export' => [
        'table_name' => env('BIOMETRIC_EXPORT_TABLE', 'biometric_attendance_logs'),
        'users_table_name' => env('BIOMETRIC_EXPORT_USERS_TABLE', 'biometric_device_users'),
        'local_staging_dir' => 'biometric-exports',
        'keep_local_sql' => (bool) env('BIOMETRIC_KEEP_LOCAL_SQL', false),
        's3_chunk_size' => max(100, (int) env('BIOMETRIC_S3_CHUNK_SIZE', 500)),
        'insert_chunk_size' => max(10, (int) env('BIOMETRIC_INSERT_CHUNK_SIZE', 40)),
        'sqlite_update_chunk_size' => max(10, (int) env('BIOMETRIC_SQLITE_UPDATE_CHUNK_SIZE', 100)),
    ],

    'logs' => [
        'per_page' => (int) env('BIOMETRIC_LOGS_PER_PAGE', 25),
        'per_page_options' => [10, 25, 50, 100],
        'retention' => [
            'archive_dir' => env('BIOMETRIC_DELETED_LOGS_DIR', 'biometric-deleted-logs'),
            'chunk_size' => max(50, (int) env('BIOMETRIC_RETENTION_CHUNK_SIZE', 500)),
            'default_months' => max(1, (int) env('BIOMETRIC_LOG_RETENTION_MONTHS', 2)),
            'min_months' => 1,
            'max_months' => 120,
        ],
    ],

    'users' => [
        'per_page' => (int) env('BIOMETRIC_USERS_PER_PAGE', 25),
        'per_page_options' => [10, 25, 50, 100],
    ],

    /*
    | Push collected logs to S3 as gzipped JSON:
    | biometric_logs/{YYYY}/{MM}/{biometric_name}/{name}_YYYYMMDDHHMMSS.json.gzip
    |
    | Defaults to the same DB_BACKUP_S3_* credentials / bucket as People360
    | (skolaris-payroll-backups-prod, ap-southeast-2).
    */
    's3' => [
        'enabled' => (bool) env('BIOMETRIC_S3_ENABLED', true),
        'disk' => env('BIOMETRIC_S3_DISK', 'backup-s3'),
        'prefix' => trim((string) env('BIOMETRIC_S3_PREFIX', 'biometric_logs'), '/'),
        'key' => env('BIOMETRIC_S3_KEY', env('DB_BACKUP_S3_KEY')),
        'secret' => env('BIOMETRIC_S3_SECRET', env('DB_BACKUP_S3_SECRET')),
        'region' => env('BIOMETRIC_S3_REGION', env('DB_BACKUP_S3_REGION', 'ap-southeast-2')),
        'bucket' => env('BIOMETRIC_S3_BUCKET', env('DB_BACKUP_S3_BUCKET')),
    ],

];
