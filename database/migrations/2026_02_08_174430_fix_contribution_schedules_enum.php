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
        // SQL Server manual update for enums/defaults
        DB::statement("ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS DF_employment_records_sss_deduction_schedule");
        DB::statement("ALTER TABLE employment_records ALTER COLUMN sss_deduction_schedule NVARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT DF_employment_records_sss_deduction_schedule DEFAULT 'default' FOR sss_deduction_schedule");
        DB::statement("UPDATE employment_records SET sss_deduction_schedule = 'default'");

        DB::statement("ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS DF_employment_records_philhealth_deduction_schedule");
        DB::statement("ALTER TABLE employment_records ALTER COLUMN philhealth_deduction_schedule NVARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT DF_employment_records_philhealth_deduction_schedule DEFAULT 'default' FOR philhealth_deduction_schedule");
        DB::statement("UPDATE employment_records SET philhealth_deduction_schedule = 'default'");

        DB::statement("ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS DF_employment_records_pagibig_deduction_schedule");
        DB::statement("ALTER TABLE employment_records ALTER COLUMN pagibig_deduction_schedule NVARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT DF_employment_records_pagibig_deduction_schedule DEFAULT 'default' FOR pagibig_deduction_schedule");
        DB::statement("UPDATE employment_records SET pagibig_deduction_schedule = 'default'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No simple rollback for this manual type change
    }
};
