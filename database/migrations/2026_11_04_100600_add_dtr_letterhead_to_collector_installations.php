<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collector_installations')) {
            return;
        }

        $missing = array_values(array_filter(
            ['dtr_address', 'dtr_contact_number', 'dtr_email', 'dtr_website'],
            fn (string $column): bool => ! Schema::hasColumn('collector_installations', $column),
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('collector_installations', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('collector_installations')) {
            return;
        }

        Schema::table('collector_installations', function (Blueprint $table) {
            foreach (['dtr_address', 'dtr_contact_number', 'dtr_email', 'dtr_website'] as $column) {
                if (Schema::hasColumn('collector_installations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
