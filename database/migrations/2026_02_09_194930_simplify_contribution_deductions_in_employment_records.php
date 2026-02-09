<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
            $table->boolean('is_sss_deducted')->default(true)->after('is_active');
            $table->boolean('is_philhealth_deducted')->default(true)->after('is_sss_deducted');
            $table->boolean('is_pagibig_deducted')->default(true)->after('is_philhealth_deducted');
            $table->boolean('is_withholding_tax_deducted')->default(true)->after('is_pagibig_deducted');
        });

        // Migrate existing data
        DB::table('employment_records')->where('sss_deduction_schedule', 'none')->update(['is_sss_deducted' => false]);
        DB::table('employment_records')->where('philhealth_deduction_schedule', 'none')->update(['is_philhealth_deducted' => false]);
        DB::table('employment_records')->where('pagibig_deduction_schedule', 'none')->update(['is_pagibig_deducted' => false]);

        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropColumn(['sss_deduction_schedule', 'philhealth_deduction_schedule', 'pagibig_deduction_schedule']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropColumn(['is_sss_deducted', 'is_philhealth_deducted', 'is_pagibig_deducted', 'is_withholding_tax_deducted']);
            $table->string('sss_deduction_schedule')->default('default');
            $table->string('philhealth_deduction_schedule')->default('default');
            $table->string('pagibig_deduction_schedule')->default('default');
        });
    }
};