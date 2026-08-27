<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_attendance_logs', function (Blueprint $table) {
            $table->string('user_name', 255)->nullable()->after('user_id');
            $table->string('card_number', 64)->nullable()->after('user_name');
        });

        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->foreignId('s3_export_batch_id')
                ->nullable()
                ->after('synced_at')
                ->constrained('log_export_batches')
                ->nullOnDelete();
        });

        Schema::table('log_export_batches', function (Blueprint $table) {
            $table->string('batch_kind', 32)->default('attendance')->after('biometric_device_id');
            $table->unsignedInteger('users_count')->default(0)->after('logs_count');
        });
    }

    public function down(): void
    {
        Schema::table('log_export_batches', function (Blueprint $table) {
            $table->dropColumn(['batch_kind', 'users_count']);
        });

        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('s3_export_batch_id');
        });

        Schema::table('biometric_attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'card_number']);
        });
    }
};
