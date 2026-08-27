<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->unsignedInteger('device_uid')->nullable();
            $table->string('user_id', 64);
            $table->string('name', 255)->nullable();
            $table->string('card_number', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['biometric_device_id', 'user_id'],
                'biometric_device_users_device_user_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_device_users');
    }
};
