<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SssContribution;
use App\Models\PhilhealthContribution;
use App\Models\PagibigContribution;

class ContributionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pag-IBIG (HDMF) - 2024/2025 Rates (Max Fund Salary increased to 10k)
        PagibigContribution::truncate();
        
        // Bracket 1: Below 1,500
        PagibigContribution::create([
            'min_salary' => 0,
            'max_salary' => 1500,
            'ee_rate' => 0.01,
            'er_rate' => 0.02,
            'max_fund_salary' => 10000 // Though functionally irrelevant for this bracket
        ]);

        // Bracket 2: 1,500 and above
        PagibigContribution::create([
            'min_salary' => 1500.01,
            'max_salary' => 9999999, // Effectively infinite
            'ee_rate' => 0.02,
            'er_rate' => 0.02,
            'max_fund_salary' => 10000 // Cap for calculation
        ]);

        // 2. PhilHealth - 2025 Rate (5%)
        PhilhealthContribution::truncate();
        
        PhilhealthContribution::create([
            'min_salary' => 10000, // Floor
            'max_salary' => 100000, // Ceiling (PHP 100k for 2024-2025)
            'rate' => 0.05, // 5%
            'er_share_percent' => 0.50,
            'ee_share_percent' => 0.50,
        ]);

        // 3. SSS - 2025 Contribution Schedule
        // Rate: 15% (10% ER, 5% EE)
        // Min MSC: 5,000 | Max MSC: 35,000
        // Compensation Range increments by 500 usually.
        // We will generate this programmatically to match the official table logic.
        
        SssContribution::truncate();

        $minMSC = 5000;
        $maxMSC = 35000;
        $increment = 500;
        
        // Special case: Below 5,250 (The first bucket usually covers up to 5,249.99 for the 5k MSC)
        // Actually, let's follow standard SSS bucket logic:
        // Range 1: Below 5,250 -> MSC 5,000
        // Range 2: 5,250 - 5,749.99 -> MSC 5,500
        // ...
        // Last Range: 34,750 and above -> MSC 35,000

        // Start loop
        $currentMSC = $minMSC;
        $startRange = 0;
        
        while ($currentMSC <= $maxMSC) {
            $endRange = $currentMSC + 249.99;
            
            if ($currentMSC == $maxMSC) {
                $endRange = 9999999; // Open ended
            }

            // Calculations 2025
            // Total 15%
            $total = $currentMSC * 0.15;
            $er = $currentMSC * 0.10; // 10%
            $ee = $currentMSC * 0.05; // 5%
            
            // EC Contribution (Employee Compensation)
            // Below 15k -> 10, Above 15k -> 30
            $ec = ($currentMSC < 15000) ? 10.00 : 30.00;
            
            // WISP Calculation (Mandatory Provident Fund)
            // Usually applies if MSC > 20,000 (Based on old table, need to verify 2025 specifics)
            // For simplicty in this generated table, we stick to the basic 15%. 
            // Real WISP is complex (excess of 20k).
            // Let's implement basic WISP logic if MSC > 20k
            $wisp_er = 0;
            $wisp_ee = 0;
            
            // Note: In 2025, regular SSS is capped. Excess is WISP.
            // But the 15% rate *includes* the WISP portion in some interpretations, 
            // OR it's separate. The standard table splits the 15% into Regular SSS + WISP.
            // For this system, we will store the GROSS amounts for simplicity unless strict WISP tracking is needed.
            // We'll store the direct calculated values.

            SssContribution::create([
                'min_salary' => $startRange,
                'max_salary' => $endRange,
                'msc' => $currentMSC,
                'er_share' => $er,
                'ee_share' => $ee,
                'ec_share' => $ec,
                'wisp_er_share' => $wisp_er, // Leaving 0 for now to avoid calc error without strict rule
                'wisp_ee_share' => $wisp_ee,
                'total_contribution' => $total + $ec // Total + EC
            ]);

            $startRange = $endRange + 0.01;
            $currentMSC += $increment;
        }
    }
}