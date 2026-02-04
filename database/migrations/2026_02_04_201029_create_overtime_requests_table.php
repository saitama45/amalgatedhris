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
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours_requested', 8, 2); // Minimum 1 hour validation in request
            $table->text('reason');
            
            // Approval
            $table->string('status')->default('Pending'); // Pending, Approved, Rejected
            $table->foreignId('approver_id')->nullable()->constrained('users'); // Removed nullOnDelete to fix SQL Server cycle
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Computation Data (Snapshotted at approval/calculation time)
            $table->boolean('is_rest_day')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_type')->nullable(); // Regular, Special, null
            $table->decimal('multiplier', 5, 4)->default(1.0); // e.g., 1.25, 1.69
            $table->decimal('hourly_rate_snapshot', 10, 2)->nullable();
            $table->decimal('payable_amount', 10, 2)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};