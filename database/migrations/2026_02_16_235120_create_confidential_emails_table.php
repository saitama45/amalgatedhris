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
        Schema::create('confidential_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->boolean('can_view_salary')->default(false);
            $table->boolean('can_manage_payroll')->default(false);
            $table->timestamps();
        });

        // Seed initial data based on previous requests
        \DB::table('confidential_emails')->insert([
            [
                'email' => 'gmcloud45@gmail.com',
                'can_view_salary' => true,
                'can_manage_payroll' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'email' => 'manilyn_edquila@yahoo.com',
                'can_view_salary' => true,
                'can_manage_payroll' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'email' => 'chris.baron@gmail.com',
                'can_view_salary' => false,
                'can_manage_payroll' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confidential_emails');
    }
};
