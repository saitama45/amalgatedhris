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
        Schema::create('overtime_rates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., regular_ot, rest_day_ot
            $table->string('name');
            $table->decimal('rate', 8, 4);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial values based on user requirements
        DB::table('overtime_rates')->insert([
            ['key' => 'regular_ot', 'name' => 'Regular OT', 'rate' => 1.25, 'description' => 'Overtime on a regular working day'],
            ['key' => 'rest_day', 'name' => 'Rest Day', 'rate' => 1.30, 'description' => 'Regular hours worked on a rest day'],
            ['key' => 'rest_day_ot', 'name' => 'Rest Day OT', 'rate' => 1.69, 'description' => 'Overtime worked on a rest day'],
            ['key' => 'regular_holiday', 'name' => 'Regular Holiday', 'rate' => 2.00, 'description' => 'Regular hours worked on a holiday'],
            ['key' => 'holiday_ot', 'name' => 'Holiday OT', 'rate' => 2.60, 'description' => 'Overtime worked on a holiday'],
            ['key' => 'holiday_rest_day_ot', 'name' => 'Holiday + Rest Day OT', 'rate' => 3.38, 'description' => 'Overtime worked on a holiday that falls on a rest day'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_rates');
    }
};