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
        Schema::table('employment_records', function (Blueprint $table) {
            $table->enum('sss_deduction_schedule', ['default', 'first_half', 'second_half', 'both', 'none'])->default('default')->after('is_active');
            $table->enum('philhealth_deduction_schedule', ['default', 'first_half', 'second_half', 'both', 'none'])->default('default')->after('sss_deduction_schedule');
            $table->enum('pagibig_deduction_schedule', ['default', 'first_half', 'second_half', 'both', 'none'])->default('default')->after('philhealth_deduction_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropColumn(['sss_deduction_schedule', 'philhealth_deduction_schedule', 'pagibig_deduction_schedule']);
        });
    }
};
