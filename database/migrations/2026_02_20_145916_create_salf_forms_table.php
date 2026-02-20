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
        Schema::create('salf_forms', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $blueprint->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $blueprint->string('period_covered');
            $blueprint->string('approved_by')->nullable();
            $blueprint->string('status')->default('draft'); // draft, submitted, approved
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salf_forms');
    }
};
