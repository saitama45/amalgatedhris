<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WithholdingTaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $years = [2023, 2024, 2025, 2026];
        
        foreach ($years as $year) {
            $brackets = [
                [
                    'effective_year' => $year,
                    'min_salary' => 0,
                    'max_salary' => 20833,
                    'base_tax' => 0,
                    'percentage' => 0,
                    'excess_over' => 0,
                    'is_active' => true,
                ],
                [
                    'effective_year' => $year,
                    'min_salary' => 20833.01,
                    'max_salary' => 33333,
                    'base_tax' => 0,
                    'percentage' => 15,
                    'excess_over' => 20833,
                    'is_active' => true,
                ],
                [
                    'effective_year' => $year,
                    'min_salary' => 33333.01,
                    'max_salary' => 66667,
                    'base_tax' => 1875,
                    'percentage' => 20,
                    'excess_over' => 33333,
                    'is_active' => true,
                ],
                [
                    'effective_year' => $year,
                    'min_salary' => 66667.01,
                    'max_salary' => 166667,
                    'base_tax' => 8541.67,
                    'percentage' => 25,
                    'excess_over' => 66667,
                    'is_active' => true,
                ],
                [
                    'effective_year' => $year,
                    'min_salary' => 166667.01,
                    'max_salary' => 666667,
                    'base_tax' => 33541.67,
                    'percentage' => 30,
                    'excess_over' => 166667,
                    'is_active' => true,
                ],
                [
                    'effective_year' => $year,
                    'min_salary' => 666667.01,
                    'max_salary' => 99999999,
                    'base_tax' => 183541.67,
                    'percentage' => 35,
                    'excess_over' => 666667,
                    'is_active' => true,
                ],
            ];

            foreach ($brackets as $bracket) {
                \App\Models\WithholdingTaxBracket::updateOrCreate(
                    ['effective_year' => $year, 'min_salary' => $bracket['min_salary']],
                    $bracket
                );
            }
        }
    }
}
