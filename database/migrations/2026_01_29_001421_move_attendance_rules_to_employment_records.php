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
            $table->dropColumn(['grace_period_minutes', 'is_ot_allowed']);
        });

        Schema::table('employment_records', function (Blueprint $table) {
            $table->integer('grace_period_minutes')->default(0)->after('work_days');
            $table->boolean('is_ot_allowed')->default(true)->after('grace_period_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->integer('grace_period_minutes')->default(0);
            $table->boolean('is_ot_allowed')->default(true);
        });

        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropColumn(['grace_period_minutes', 'is_ot_allowed']);
        });
    }
};