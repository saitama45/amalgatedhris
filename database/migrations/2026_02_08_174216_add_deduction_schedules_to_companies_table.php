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
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('sss_payout_schedule', ['first_half', 'second_half', 'both'])->default('second_half');
            $table->enum('philhealth_payout_schedule', ['first_half', 'second_half', 'both'])->default('second_half');
            $table->enum('pagibig_payout_schedule', ['first_half', 'second_half', 'both'])->default('second_half');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['sss_payout_schedule', 'philhealth_payout_schedule', 'pagibig_payout_schedule']);
        });
    }
};
