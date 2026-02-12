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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('qr_code', 100)->nullable()->after('employee_code');
        });

        // For SQL Server, we need a filtered unique index to allow multiple NULLs
        if (config('database.default') === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX employees_qr_code_unique ON employees (qr_code) WHERE qr_code IS NOT NULL');
        } else {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('qr_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (config('database.default') === 'sqlsrv') {
                DB::statement('DROP INDEX employees_qr_code_unique ON employees');
            } else {
                $table->dropUnique(['qr_code']);
            }
            $table->dropColumn('qr_code');
        });
    }
};
