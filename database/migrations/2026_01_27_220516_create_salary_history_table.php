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
        Schema::create('salary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_record_id')->constrained('employment_records')->cascadeOnDelete();
            $table->decimal('basic_rate', 12, 2);
            $table->decimal('allowance', 12, 2)->default(0);
            $table->date('effective_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_history');
    }
};