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
        Schema::table('employment_records', function (Blueprint $table) {
            $table->decimal('allowance_15th', 15, 2)->default(0)->after('allowance');
            $table->decimal('allowance_30th', 15, 2)->default(0)->after('allowance_15th');
        });

        // Split current allowance for existing records
        DB::table('employment_records')->get()->each(function ($record) {
            $half = $record->allowance / 2;
            DB::table('employment_records')->where('id', $record->id)->update([
                'allowance_15th' => $half,
                'allowance_30th' => $half,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
            $table->dropColumn(['allowance_15th', 'allowance_30th']);
        });
    }
};