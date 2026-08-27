<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_attendance_logs', function (Blueprint $table) {
            $table->timestamp('s3_pushed_at')->nullable()->after('log_export_batch_id');
            $table->string('s3_object_key', 512)->nullable()->after('s3_pushed_at');

            $table->index('s3_pushed_at', 'biometric_logs_s3_pushed_at_index');
        });

        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->timestamp('s3_pushed_at')->nullable()->after('s3_export_batch_id');
            $table->string('s3_object_key', 512)->nullable()->after('s3_pushed_at');
        });

        if (Schema::hasTable('log_export_batches')) {
            DB::table('biometric_attendance_logs')
                ->whereNotNull('log_export_batch_id')
                ->whereNull('s3_pushed_at')
                ->orderBy('id')
                ->chunkById(500, function ($logs): void {
                    foreach ($logs as $log) {
                        $batch = DB::table('log_export_batches')
                            ->where('id', $log->log_export_batch_id)
                            ->whereNotNull('uploaded_at')
                            ->whereNotNull('s3_key')
                            ->first();

                        if ($batch === null) {
                            continue;
                        }

                        DB::table('biometric_attendance_logs')
                            ->where('id', $log->id)
                            ->update([
                                's3_pushed_at' => $batch->uploaded_at,
                                's3_object_key' => $batch->s3_key,
                            ]);
                    }
                });

            DB::table('biometric_device_users')
                ->whereNotNull('s3_export_batch_id')
                ->whereNull('s3_pushed_at')
                ->orderBy('id')
                ->chunkById(500, function ($users): void {
                    foreach ($users as $user) {
                        $batch = DB::table('log_export_batches')
                            ->where('id', $user->s3_export_batch_id)
                            ->whereNotNull('uploaded_at')
                            ->whereNotNull('s3_key')
                            ->first();

                        if ($batch === null) {
                            continue;
                        }

                        DB::table('biometric_device_users')
                            ->where('id', $user->id)
                            ->update([
                                's3_pushed_at' => $batch->uploaded_at,
                                's3_object_key' => $batch->s3_key,
                            ]);
                    }
                });

            // Logs tied to a batch that never reached S3 — allow retry.
            DB::table('biometric_attendance_logs')
                ->whereNotNull('log_export_batch_id')
                ->whereNull('s3_pushed_at')
                ->update(['log_export_batch_id' => null]);

            DB::table('biometric_device_users')
                ->whereNotNull('s3_export_batch_id')
                ->whereNull('s3_pushed_at')
                ->update(['s3_export_batch_id' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->dropColumn(['s3_pushed_at', 's3_object_key']);
        });

        Schema::table('biometric_attendance_logs', function (Blueprint $table) {
            $table->dropIndex('biometric_logs_s3_pushed_at_index');
            $table->dropColumn(['s3_pushed_at', 's3_object_key']);
        });
    }
};
