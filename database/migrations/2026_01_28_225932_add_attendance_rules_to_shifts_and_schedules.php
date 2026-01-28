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
        Schema::table('shifts', function (Blueprint $table) {
            $table->integer('grace_period_minutes')->default(0)->after('end_time');
            $table->boolean('is_ot_allowed')->default(true)->after('grace_period_minutes');
        });

        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->integer('grace_period_minutes')->default(0)->after('end_time');
            $table->boolean('is_ot_allowed')->default(true)->after('grace_period_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['grace_period_minutes', 'is_ot_allowed']);
        });

        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropColumn(['grace_period_minutes', 'is_ot_allowed']);
        });
    }
};