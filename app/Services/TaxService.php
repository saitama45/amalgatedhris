<?php

namespace App\Services;

use App\Models\WithholdingTaxBracket;

class TaxService
{
    /**
     * Calculate monthly withholding tax based on taxable income.
     * Taxable Income = Gross Pay - (SSS + PhilHealth + Pag-IBIG)
     */
    public function calculateMonthlyTax(float $taxableIncome): float
    {
        $bracket = WithholdingTaxBracket::active()
            ->where('min_salary', '<=', $taxableIncome)
            ->where('max_salary', '>=', $taxableIncome)
            ->first();

        if (!$bracket) {
            // Fallback for extreme cases
            if ($taxableIncome > 666666) {
                $bracket = WithholdingTaxBracket::active()->orderBy('max_salary', 'desc')->first();
            } else {
                return 0;
            }
        }

        if (!$bracket) return 0;

        $excess = max(0, $taxableIncome - $bracket->excess_over);
        $tax = $bracket->base_tax + ($excess * ($bracket->percentage / 100));

        return round($tax, 2);
    }
}
