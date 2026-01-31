<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['sss_contributions', 'philhealth_contributions', 'pagibig_contributions'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->year('effective_year')->default(2025)->after('id')->index();
                $table->boolean('is_active')->default(true)->after('effective_year');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['sss_contributions', 'philhealth_contributions', 'pagibig_contributions'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['effective_year', 'is_active']);
            });
        }
    }
};
