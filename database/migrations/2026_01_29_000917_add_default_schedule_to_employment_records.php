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
        Schema::table('employment_records', function (Blueprint $table) {
            $table->foreignId('default_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('work_days')->default('1,2,3,4,5'); // 0=Sun, 1=Mon, etc. Default Mon-Fri
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropForeign(['default_shift_id']);
            $table->dropColumn(['default_shift_id', 'work_days']);
        });
    }
};