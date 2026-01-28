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
            $table->text('address')->nullable()->after('civil_status');
            $table->string('gender')->nullable()->after('civil_status');
            $table->string('emergency_contact')->nullable()->after('address');
            $table->string('emergency_contact_number')->nullable()->after('emergency_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['address', 'gender', 'emergency_contact', 'emergency_contact_number']);
        });
    }
};