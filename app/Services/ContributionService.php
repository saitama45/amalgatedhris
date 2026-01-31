<?php

namespace App\Services;

use App\Models\SssContribution;
use App\Models\PhilhealthContribution;
use App\Models\PagibigContribution;

class ContributionService
{
    /**
     * Calculate all government contributions for a given monthly basic salary.
     *
     * @param float $salary
     * @return array
     */
    public function calculate(float $salary): array
    {
        return [
            'sss' => $this->calculateSSS($salary),
            'philhealth' => $this->calculatePhilHealth($salary),
            'pagibig' => $this->calculatePagIBIG($salary),
        ];
    }

    public function calculateSSS(float $salary)
    {
        // Find the bracket where salary falls
        $bracket = SssContribution::active()
            ->where('min_salary', '<=', $salary)
            ->where('max_salary', '>=', $salary)
            ->first();

        // Fallback for out of bounds (usually high income hits last bracket, low income hits first)
        if (!$bracket) {
            if ($salary > 30000) { // Safety check, grab max
                $bracket = SssContribution::active()->orderBy('max_salary', 'desc')->first();
            } else {
                 $bracket = SssContribution::active()->orderBy('min_salary', 'asc')->first();
            }
        }

        return [
            'er' => $bracket->er_share + $bracket->ec_share,
            'ee' => $bracket->ee_share,
            'ec' => $bracket->ec_share,
            'total' => $bracket->total_contribution,
            'msc' => $bracket->msc
        ];
    }

    public function calculatePhilHealth(float $salary)
    {
        $config = PhilhealthContribution::active()->first(); // Assumes single active config row
        
        if (!$config) return ['er' => 0, 'ee' => 0, 'total' => 0];

        // Apply Floor and Ceiling
        $computableSalary = $salary;
        if ($salary < $config->min_salary) $computableSalary = $config->min_salary;
        if ($salary > $config->max_salary) $computableSalary = $config->max_salary;

        $totalPremium = $computableSalary * $config->rate;
        
        return [
            'er' => $totalPremium * $config->er_share_percent,
            'ee' => $totalPremium * $config->ee_share_percent,
            'total' => $totalPremium
        ];
    }

    public function calculatePagIBIG(float $salary)
    {
        // Find bracket (based on 1500 threshold usually)
        $bracket = PagibigContribution::active()
            ->where('min_salary', '<=', $salary)
            ->where('max_salary', '>=', $salary)
            ->first();

        if (!$bracket) {
             // Default to highest if not found (unlikely with infinite max)
             $bracket = PagibigContribution::active()->orderBy('min_salary', 'desc')->first();
        }

        // Apply Max Fund Salary Cap
        $computableSalary = $salary;
        if ($salary > $bracket->max_fund_salary) {
            $computableSalary = $bracket->max_fund_salary;
        }

        $ee = $computableSalary * $bracket->ee_rate;
        $er = $computableSalary * $bracket->er_rate;

        return [
            'er' => $er,
            'ee' => $ee,
            'total' => $ee + $er
        ];
    }
}
