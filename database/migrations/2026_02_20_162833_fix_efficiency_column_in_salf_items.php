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
        Schema::table('salf_items', function (Blueprint $table) {
            $table->dropColumn('efficiency');
        });

        Schema::table('salf_items', function (Blueprint $table) {
            $table->decimal('efficiency', 8, 2)->nullable()->after('target_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salf_items', function (Blueprint $table) {
            $table->dropColumn('efficiency');
        });

        Schema::table('salf_items', function (Blueprint $table) {
            $table->decimal('efficiency', 5, 2)->virtualAs('(actual_value / NULLIF(target_value, 0)) * 100');
        });
    }
};
