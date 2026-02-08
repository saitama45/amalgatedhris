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
        Schema::create('withholding_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->integer('effective_year');
            $table->decimal('min_salary', 12, 2);
            $table->decimal('max_salary', 12, 2);
            $table->decimal('base_tax', 12, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('excess_over', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholding_tax_brackets');
    }
};
