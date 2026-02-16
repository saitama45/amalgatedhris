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
        Schema::create('id_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('front_image_path');
            $table->string('back_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable(); // For positioning details if needed later
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_templates');
    }
};
