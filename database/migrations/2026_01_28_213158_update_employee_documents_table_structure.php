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
        // Clear existing data as it's incompatible with the new strict structure
        DB::table('employee_documents')->truncate();

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropColumn('doc_type');
            $table->foreignId('document_type_id')->after('employee_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
            $table->string('doc_type');
        });
    }
};
