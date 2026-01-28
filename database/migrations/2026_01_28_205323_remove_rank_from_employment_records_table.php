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
        // For SQL Server, we need to drop the constraint first.
        // We'll try to find it dynamically or just attempt to drop it if we knew the name,
        // but since the name is auto-generated, we should look it up or rely on raw SQL to clean it up.
        
        // However, Laravel's schema builder usually handles basic column dropping.
        // The error `CK__employment__rank__...` indicates a Check Constraint (likely from the Enum).
        
        // Let's use raw statement to drop the constraint if using SQL Server.
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("
                DECLARE @sql NVARCHAR(MAX) = '';
                
                -- Drop default constraints
                SELECT @sql += 'ALTER TABLE employment_records DROP CONSTRAINT ' + name + ';'
                FROM sys.default_constraints
                WHERE parent_object_id = OBJECT_ID('employment_records')
                AND parent_column_id = COLUMNPROPERTY(OBJECT_ID('employment_records'), 'rank', 'ColumnId');

                -- Drop check constraints
                SELECT @sql += 'ALTER TABLE employment_records DROP CONSTRAINT ' + name + ';'
                FROM sys.check_constraints
                WHERE parent_object_id = OBJECT_ID('employment_records')
                AND parent_column_id = COLUMNPROPERTY(OBJECT_ID('employment_records'), 'rank', 'ColumnId');
                
                EXEC sp_executesql @sql;
            ");
        }

        Schema::table('employment_records', function (Blueprint $table) {
             $table->dropColumn('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_records', function (Blueprint $table) {
             // Re-adding the column without the specific constraint name (Laravel will generate a new one)
            $table->enum('rank', ['RankAndFile', 'Supervisor', 'Manager', 'Executive'])->default('RankAndFile');
        });
    }
};
