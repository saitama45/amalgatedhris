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
        Schema::create('salf_items', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('salf_form_id')->constrained()->cascadeOnDelete();
            $blueprint->string('section')->nullable(); // Operating Manual, AGC Projects, etc.
            $blueprint->text('area_of_concern');
            $blueprint->text('action_plan');
            $blueprint->string('support_group')->nullable();
            $blueprint->date('target_date');
            $blueprint->decimal('actual_value', 8, 2)->default(0);
            $blueprint->decimal('target_value', 8, 2)->default(100);
            $blueprint->decimal('efficiency', 5, 2)->virtualAs('(actual_value / NULLIF(target_value, 0)) * 100');
            $blueprint->text('remarks')->nullable();
            $blueprint->integer('order')->default(0);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salf_items');
    }
};
