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
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained()->cascadeOnDelete();
            
            // Calculation
            $table->enum('calculation_type', ['fixed_amount', 'percentage'])->default('fixed_amount');
            $table->decimal('amount', 12, 2); // The value (e.g. 500 or 10.00)
            
            // Frequency
            $table->enum('frequency', ['once_a_month', 'semimonthly'])->default('semimonthly');
            $table->enum('schedule', ['first_half', 'second_half', 'both'])->default('both'); 
            
            // Loan specifics
            $table->decimal('total_amount', 12, 2)->nullable(); // Total loan amount
            $table->decimal('remaining_balance', 12, 2)->nullable(); // Current balance
            
            // Validity
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, completed, cancelled
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');
    }
};