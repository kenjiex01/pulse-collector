<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->string('privilege', 32)->default('User')->after('card_number');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->dropColumn('privilege');
        });
    }
};
