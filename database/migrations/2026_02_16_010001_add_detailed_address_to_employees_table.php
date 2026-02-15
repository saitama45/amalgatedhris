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
            $table->string('home_no_street')->nullable()->after('address');
            $table->string('barangay')->nullable()->after('home_no_street');
            $table->string('city')->nullable()->after('barangay');
            $table->string('region')->nullable()->after('city');
            $table->string('zip_code')->nullable()->after('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['home_no_street', 'barangay', 'city', 'region', 'zip_code']);
        });
    }
};
