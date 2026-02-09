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
        // 1. Add columns to employment_records
        Schema::table('employment_records', function (Blueprint $table) {
            $table->decimal('basic_rate', 15, 2)->default(0)->after('position_id');
            $table->decimal('allowance', 15, 2)->default(0)->after('basic_rate');
        });

        // 2. Migrate existing data from salary_history
        // We take the latest salary entry for each employment record
        $histories = DB::table('salary_history')
            ->orderBy('effective_date', 'asc') // Apply from oldest to newest so newest wins in update
            ->get();

        foreach ($histories as $history) {
            DB::table('employment_records')
                ->where('id', $history->employment_record_id)
                ->update([
                    'basic_rate' => $history->basic_rate,
                    'allowance' => $history->allowance,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropColumn(['basic_rate', 'allowance']);
        });
    }
};
