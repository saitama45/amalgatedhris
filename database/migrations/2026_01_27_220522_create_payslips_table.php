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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_pay', 12, 2);
            $table->decimal('ot_pay', 12, 2)->default(0);
            $table->decimal('sss_deduction', 12, 2)->default(0);
            $table->decimal('philhealth_ded', 12, 2)->default(0);
            $table->decimal('pagibig_ded', 12, 2)->default(0);
            $table->decimal('tax_withheld', 12, 2)->default(0);
            $table->decimal('loan_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->decimal('months_worked', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};