<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('collector_installations', 'log_retention_months')) {
            return;
        }

        Schema::table('collector_installations', function (Blueprint $table) {
            $table->unsignedSmallInteger('log_retention_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('collector_installations', function (Blueprint $table) {
            $table->dropColumn('log_retention_months');
        });
    }
};
