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
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('civil_status')->nullable()->after('phone');
            $table->string('gender')->nullable()->after('civil_status');
            $table->date('birthday')->nullable()->after('gender');
            $table->string('home_no_street')->nullable()->after('birthday');
            $table->string('barangay')->nullable()->after('home_no_street');
            $table->string('city')->nullable()->after('barangay');
            $table->string('region')->nullable()->after('city');
            $table->string('zip_code')->nullable()->after('region');
            $table->text('skills')->nullable()->after('zip_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'civil_status',
                'gender',
                'birthday',
                'home_no_street',
                'barangay',
                'city',
                'region',
                'zip_code',
                'skills',
            ]);
        });
    }
};
