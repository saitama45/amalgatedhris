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

            // Add the new constraint with 'Incomplete'
            DB::statement("ALTER TABLE attendance_logs ADD CHECK (status IN ('Present', 'Late', 'Absent', 'Leave', 'Rest Day', 'Incomplete'))");
        } else {
            // Fallback for other drivers (though strictly might require doctrine/dbal without it)
            // We use raw SQL for SQLite/MySQL to be safe without DBAL
            if ($driver === 'sqlite') {
                // SQLite doesn't support modifying constraints easily, usually requires table recreation.
                // We'll skip for now as the target is SQL Server.
            } elseif ($driver === 'mysql') {
                 DB::statement("ALTER TABLE attendance_logs MODIFY COLUMN status ENUM('Present', 'Late', 'Absent', 'Leave', 'Rest Day', 'Incomplete') NOT NULL DEFAULT 'Absent'");
            } elseif ($driver === 'pgsql') {
                // Postgres Enums are types
                DB::statement("ALTER TYPE attendance_logs_status_enum ADD VALUE 'Incomplete'");
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
            
            DB::statement("ALTER TABLE attendance_logs ADD CHECK (status IN ('Present', 'Late', 'Absent', 'Leave', 'Rest Day'))");
        } else {
            if ($driver === 'mysql') {
                 DB::statement("ALTER TABLE attendance_logs MODIFY COLUMN status ENUM('Present', 'Late', 'Absent', 'Leave', 'Rest Day') NOT NULL DEFAULT 'Absent'");
            }
            // Postgres rollback is hard (cannot remove value from enum easily)
        }
    }
};
