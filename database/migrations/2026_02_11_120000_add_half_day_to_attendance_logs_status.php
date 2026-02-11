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
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            // Find the constraint name dynamically
            $results = DB::select("
                SELECT name 
                FROM sys.check_constraints 
                WHERE parent_object_id = OBJECT_ID('attendance_logs') 
                AND definition LIKE '%status%'
            ");

            foreach ($results as $result) {
                DB::statement("ALTER TABLE attendance_logs DROP CONSTRAINT " . $result->name);
            }

            // Add the new constraint with 'Half Day'
            DB::statement("ALTER TABLE attendance_logs ADD CHECK (status IN ('Present', 'Late', 'Absent', 'Leave', 'Rest Day', 'Incomplete', 'Half Day'))");
        } else {
            if ($driver === 'mysql') {
                 DB::statement("ALTER TABLE attendance_logs MODIFY COLUMN status ENUM('Present', 'Late', 'Absent', 'Leave', 'Rest Day', 'Incomplete', 'Half Day') NOT NULL DEFAULT 'Absent'");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TYPE attendance_logs_status_enum ADD VALUE 'Half Day'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
             $results = DB::select("
                SELECT name 
                FROM sys.check_constraints 
                WHERE parent_object_id = OBJECT_ID('attendance_logs') 
                AND definition LIKE '%status%'
            ");

            foreach ($results as $result) {
                DB::statement("ALTER TABLE attendance_logs DROP CONSTRAINT " . $result->name);
            }
            
            DB::statement("ALTER TABLE attendance_logs ADD CHECK (status IN ('Present', 'Late', 'Absent', 'Leave', 'Rest Day', 'Incomplete'))");
        } else {
            if ($driver === 'mysql') {
                 DB::statement("ALTER TABLE attendance_logs MODIFY COLUMN status ENUM('Present', 'Late', 'Absent', 'Leave', 'Rest Day', 'Incomplete') NOT NULL DEFAULT 'Absent'");
            }
        }
    }
};
