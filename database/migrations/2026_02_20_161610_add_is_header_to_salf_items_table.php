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
            $table->boolean('is_header')->default(false)->after('salf_form_id');
            $table->text('area_of_concern')->nullable()->change();
            $table->text('action_plan')->nullable()->change();
            $table->date('target_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salf_items', function (Blueprint $table) {
            $table->dropColumn('is_header');
            $table->text('area_of_concern')->nullable(false)->change();
            $table->text('action_plan')->nullable(false)->change();
            $table->date('target_date')->nullable(false)->change();
        });
    }
};
