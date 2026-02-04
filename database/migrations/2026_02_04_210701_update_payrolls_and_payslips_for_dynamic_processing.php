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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->text('remarks')->nullable()->after('status');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('allowances', 12, 2)->default(0)->after('basic_pay');
            $table->decimal('late_deduction', 12, 2)->default(0)->after('ot_pay');
            $table->decimal('undertime_deduction', 12, 2)->default(0)->after('late_deduction');
            $table->decimal('other_deductions', 12, 2)->default(0)->after('loan_deductions');
            $table->decimal('gross_pay', 12, 2)->default(0)->after('basic_pay');
            
            // Add breakdown JSON for detailed view if needed (e.g. which OT types)
            $table->json('details')->nullable()->after('net_pay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->dropColumn('remarks');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn([
                'allowances', 
                'late_deduction', 
                'undertime_deduction', 
                'other_deductions',
                'gross_pay',
                'details'
            ]);
        });
    }
};