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
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->boolean('is_ob')->default(false)->after('ot_minutes');
            $table->decimal('in_latitude', 10, 8)->nullable()->after('is_ob');
            $table->decimal('in_longitude', 11, 8)->nullable()->after('in_latitude');
            $table->string('in_photo_path')->nullable()->after('in_longitude');
            $table->string('in_location_url')->nullable()->after('in_photo_path');
            
            $table->decimal('out_latitude', 10, 8)->nullable()->after('in_location_url');
            $table->decimal('out_longitude', 11, 8)->nullable()->after('out_latitude');
            $table->string('out_photo_path')->nullable()->after('out_longitude');
            $table->string('out_location_url')->nullable()->after('out_photo_path');
            
            $table->boolean('is_approved')->default(true)->after('out_location_url'); // Default to true for now as per "can add their attendance", but we can change if manual approval is needed.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'is_ob',
                'in_latitude',
                'in_longitude',
                'in_photo_path',
                'in_location_url',
                'out_latitude',
                'out_longitude',
                'out_photo_path',
                'out_location_url',
                'is_approved'
            ]);
        });
    }
};
