<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collector_installations', function (Blueprint $table) {
            $table->date('attendance_start_date')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('collector_installations', function (Blueprint $table) {
            $table->dropColumn('attendance_start_date');
        });
    }
};
