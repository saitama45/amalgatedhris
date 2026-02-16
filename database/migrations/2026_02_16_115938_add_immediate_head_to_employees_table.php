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
        Schema::table('employees', function (Blueprint $table) {
            // Using NO ACTION because SQL Server is sensitive to self-referencing delete cascades
            $table->unsignedBigInteger('immediate_head_id')->nullable()->after('user_id');
            $table->foreign('immediate_head_id')->references('id')->on('employees')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['immediate_head_id']);
            $table->dropColumn('immediate_head_id');
        });
    }
};
