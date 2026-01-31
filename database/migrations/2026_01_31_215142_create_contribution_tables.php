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
        // SSS Contribution Table (Based on MSC)
        Schema::create('sss_contributions', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_salary', 10, 2);
            $table->decimal('max_salary', 10, 2);
            $table->decimal('msc', 10, 2); // Monthly Salary Credit
            $table->decimal('er_share', 10, 2); // Regular ER
            $table->decimal('ee_share', 10, 2); // Regular EE
            $table->decimal('ec_share', 10, 2); // Employee Compensation (ER only)
            $table->decimal('wisp_er_share', 10, 2)->default(0); // WISP (Provident Fund)
            $table->decimal('wisp_ee_share', 10, 2)->default(0);
            $table->decimal('total_contribution', 10, 2);
            $table->timestamps();
        });

        // PhilHealth Contribution Table (Usually computed by rate, but config is needed)
        Schema::create('philhealth_contributions', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_salary', 10, 2); // Floor (e.g., 10k)
            $table->decimal('max_salary', 10, 2); // Ceiling (e.g., 100k)
            $table->decimal('rate', 5, 4); // e.g., 0.0500 (5%)
            $table->decimal('er_share_percent', 5, 4)->default(0.50); // 50%
            $table->decimal('ee_share_percent', 5, 4)->default(0.50); // 50%
            $table->boolean('is_fixed_amount')->default(false); // For special cases if any
            $table->decimal('fixed_amount', 10, 2)->nullable();
            $table->timestamps();
        });

        // Pag-IBIG Contribution Table
        Schema::create('pagibig_contributions', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_salary', 10, 2);
            $table->decimal('max_salary', 10, 2);
            $table->decimal('ee_rate', 5, 4); // 1% or 2%
            $table->decimal('er_rate', 5, 4); // 2%
            $table->decimal('max_fund_salary', 10, 2); // Cap (e.g., 10k in 2025)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagibig_contributions');
        Schema::dropIfExists('philhealth_contributions');
        Schema::dropIfExists('sss_contributions');
    }
};
