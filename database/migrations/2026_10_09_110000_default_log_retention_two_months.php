<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collector_installations') || ! Schema::hasColumn('collector_installations', 'log_retention_months')) {
            return;
        }

        $defaultMonths = max(1, (int) config('biometric.logs.retention.default_months', 2));

        DB::table('collector_installations')
            ->whereNull('log_retention_months')
            ->update(['log_retention_months' => $defaultMonths]);
    }

    public function down(): void
    {
        // Non-reversible: prior null vs explicit 2 cannot be distinguished.
    }
};
