<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->string('name');
            $table->string('model', 64)->default('K30');
            $table->string('serial_number', 64)->nullable();
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('port')->default(4370);
            $table->unsignedInteger('comm_key')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_collected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['campus_id', 'ip_address', 'port']);
        });

        Schema::create('log_export_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('biometric_device_id')->nullable()->constrained('biometric_devices')->nullOnDelete();
            $table->unsignedInteger('logs_count')->default(0);
            $table->string('sql_filename');
            $table->string('s3_key')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('biometric_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('biometric_device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->unsignedInteger('device_uid')->nullable();
            $table->string('user_id', 64);
            $table->dateTime('punched_at');
            $table->string('verify_mode', 32)->nullable();
            $table->string('punch_state', 32)->nullable();
            $table->foreignId('log_export_batch_id')->nullable()->constrained('log_export_batches')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['biometric_device_id', 'user_id', 'punched_at'],
                'biometric_logs_device_user_punch_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_attendance_logs');
        Schema::dropIfExists('log_export_batches');
        Schema::dropIfExists('biometric_devices');
        Schema::dropIfExists('campuses');
    }
};
