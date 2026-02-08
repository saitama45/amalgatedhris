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
        $columns = ['sss_deduction_schedule', 'philhealth_deduction_schedule', 'pagibig_deduction_schedule'];

        foreach ($columns as $column) {
            // 1. Find and drop ANY existing default constraint for this column
            $constraint = DB::select("
                SELECT d.name 
                FROM sys.default_constraints d 
                INNER JOIN sys.columns c ON d.parent_column_id = c.column_id AND d.parent_object_id = c.object_id
                WHERE c.object_id = OBJECT_ID('employment_records') AND c.name = '$column'
            ");

            if (!empty($constraint)) {
                $name = $constraint[0]->name;
                DB::statement("ALTER TABLE employment_records DROP CONSTRAINT $name");
            }

            // 2. Drop any existing check constraints (common with Laravel enums on SQL Server)
            $checks = DB::select("
                SELECT name FROM sys.check_constraints 
                WHERE parent_object_id = OBJECT_ID('employment_records') 
                AND definition LIKE '%$column%'
            ");
            
            foreach ($checks as $check) {
                $name = $check->name;
                DB::statement("ALTER TABLE employment_records DROP CONSTRAINT $name");
            }

            // 3. Alter the column to NVARCHAR (avoiding limited enum issues)
            DB::statement("ALTER TABLE employment_records ALTER COLUMN $column NVARCHAR(50) NOT NULL");

            // 4. Add the new 'default' constraint
            DB::statement("ALTER TABLE employment_records ADD DEFAULT 'default' FOR $column");
            
            // 5. Update existing data to use the new default string
            DB::statement("UPDATE employment_records SET $column = 'default'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No simple rollback for this manual type change
    }
};
