<?php

namespace App\Domain\HRM;

use Carbon\Carbon;

/**
 * India Statutory Payroll Deduction & Compliance Calculator
 *
 * Implements official Government of India statutory payroll compliance:
 * 1. Employees' Provident Fund (EPF / EPS):
 *    - Applicable if basic salary or opt-in.
 *    - Statutory Basic Wage Ceiling: ₹15,000 / month.
 *    - Employee EPF: 12% of Basic (capped at ₹1,800 unless unrestricted).
 *    - Employer EPF: 3.67% (Basic up to ceiling).
 *    - Employer EPS (Pension): 8.33% (capped at ₹1,250 / month).
 * 2. Employees' State Insurance (ESIC):
 *    - Applicable if Gross Wage <= ₹21,000 / month (or ₹25,000 for persons with disability).
 *    - Employee Contribution: 0.75% of Gross.
 *    - Employer Contribution: 3.25% of Gross.
 * 3. Professional Tax (PT):
 *    - Standard state slab (e.g. Maharashtra, Karnataka, Telangana, Tamil Nadu).
 *    - Default standard slab:
 *      * Gross <= ₹7,500: ₹0
 *      * Gross ₹7,501 - ₹10,000: ₹175
 *      * Gross > ₹10,000: ₹200 (February ₹300).
 * 4. Tax Deducted at Source (TDS / Income Tax under Sec 192):
 *    - Default New Tax Regime (FY 2024-26 / Sec 115BAC):
 *      * Annual taxable income up to ₹3,00,000: Nil
 *      * ₹3,00,001 to ₹7,00,000: 5% (with Sec 87A rebate for income up to ₹7,00,000 -> Nil tax)
 *      * Standard deduction of ₹75,000 applied annually.
 *      * Monthly TDS = (Annual Estimated Tax / 12).
 */
class IndiaStatutoryCalculator
{
    public const EPF_WAGE_CEILING = 15000.00;
    public const EPF_EMPLOYEE_RATE = 0.12;
    public const EPF_EMPLOYER_EPF_RATE = 0.0367;
    public const EPF_EMPLOYER_EPS_RATE = 0.0833;
    public const EPS_MAX_MONTHLY = 1250.00;

    public const ESI_WAGE_CEILING = 21000.00;
    public const ESI_EMPLOYEE_RATE = 0.0075;
    public const ESI_EMPLOYER_RATE = 0.0325;

    public const ANNUAL_STANDARD_DEDUCTION = 75000.00;

    /**
     * Compute all statutory components for an employee.
     *
     * @param float $basicSalary
     * @param float $grossEarnings Total earnings including allowances
     * @param array<string, mixed> $options Custom employee flags (e.g. state, is_epf_exempt, is_esi_exempt, tds_declared_monthly)
     * @return array{
     *   employee_deductions: array<int, array{name: string, type: string, amount: float, code: string}>,
     *   employer_contributions: array<int, array{name: string, amount: float, code: string}>,
     *   total_statutory_deductions: float,
     *   total_employer_cost: float,
     *   breakdown: array<string, float>
     * }
     */
    public function calculate(float $basicSalary, float $grossEarnings, array $options = []): array
    {
        $employeeDeductions = [];
        $employerContributions = [];
        $breakdown = [];

        // ── 1. Employees' Provident Fund (EPF) ──────────────────────────────
        $epfEligible = ! ($options['is_epf_exempt'] ?? false);
        if ($epfEligible && $basicSalary > 0) {
            $capAtCeiling = $options['epf_enforce_ceiling'] ?? true;
            $epfWage = $capAtCeiling ? min($basicSalary, self::EPF_WAGE_CEILING) : $basicSalary;

            $employeeEpf = round($epfWage * self::EPF_EMPLOYEE_RATE, 2);
            $employerEps = min(round($epfWage * self::EPF_EMPLOYER_EPS_RATE, 2), self::EPS_MAX_MONTHLY);
            $employerEpf = max(0.0, round($epfWage * self::EPF_EMPLOYEE_RATE, 2) - $employerEps);

            $employeeDeductions[] = [
                'name' => 'Provident Fund (EPF Employee 12%)',
                'type' => 'deduction',
                'amount' => $employeeEpf,
                'code' => 'STAT_EPF_EE',
            ];

            $employerContributions[] = [
                'name' => 'Employer EPF (3.67%)',
                'amount' => $employerEpf,
                'code' => 'STAT_EPF_ER',
            ];
            $employerContributions[] = [
                'name' => 'Employer EPS (Pension 8.33%)',
                'amount' => $employerEps,
                'code' => 'STAT_EPS_ER',
            ];

            $breakdown['epf_employee'] = $employeeEpf;
            $breakdown['epf_employer'] = $employerEpf;
            $breakdown['eps_employer'] = $employerEps;
        }

        // ── 2. Employees' State Insurance (ESI) ─────────────────────────────
        $esiEligible = ! ($options['is_esi_exempt'] ?? false) && ($grossEarnings <= self::ESI_WAGE_CEILING);
        if ($esiEligible && $grossEarnings > 0) {
            $employeeEsi = round($grossEarnings * self::ESI_EMPLOYEE_RATE, 2);
            $employerEsi = round($grossEarnings * self::ESI_EMPLOYER_RATE, 2);

            $employeeDeductions[] = [
                'name' => 'Employee State Insurance (ESI 0.75%)',
                'type' => 'deduction',
                'amount' => $employeeEsi,
                'code' => 'STAT_ESI_EE',
            ];

            $employerContributions[] = [
                'name' => 'Employer ESI (3.25%)',
                'amount' => $employerEsi,
                'code' => 'STAT_ESI_ER',
            ];

            $breakdown['esi_employee'] = $employeeEsi;
            $breakdown['esi_employer'] = $employerEsi;
        }

        // ── 3. Professional Tax (PT) ────────────────────────────────────────
        $ptEligible = ! ($options['is_pt_exempt'] ?? false);
        if ($ptEligible && $grossEarnings > 0) {
            $month = isset($options['period_end']) ? Carbon::parse($options['period_end'])->month : Carbon::now()->month;
            $ptAmount = $this->calculateProfessionalTax($grossEarnings, $month, $options['state'] ?? 'standard');

            if ($ptAmount > 0) {
                $employeeDeductions[] = [
                    'name' => 'Professional Tax (PT)',
                    'type' => 'deduction',
                    'amount' => $ptAmount,
                    'code' => 'STAT_PT',
                ];
                $breakdown['professional_tax'] = $ptAmount;
            }
        }

        // ── 4. Tax Deducted at Source (TDS / Income Tax) ───────────────────
        $tdsAmount = 0.0;
        if (isset($options['tds_monthly']) && is_numeric($options['tds_monthly'])) {
            $tdsAmount = round((float) $options['tds_monthly'], 2);
        } elseif (($options['auto_tds'] ?? true) && $grossEarnings > 0) {
            $tdsAmount = $this->calculateEstimatedMonthlyTds($grossEarnings);
        }

        if ($tdsAmount > 0) {
            $employeeDeductions[] = [
                'name' => 'Tax Deducted at Source (TDS)',
                'type' => 'deduction',
                'amount' => $tdsAmount,
                'code' => 'STAT_TDS',
            ];
            $breakdown['tds'] = $tdsAmount;
        }

        $totalStatutoryDeductions = array_sum(array_column($employeeDeductions, 'amount'));
        $totalEmployerCost = array_sum(array_column($employerContributions, 'amount'));

        return [
            'employee_deductions' => $employeeDeductions,
            'employer_contributions' => $employerContributions,
            'total_statutory_deductions' => round($totalStatutoryDeductions, 2),
            'total_employer_cost' => round($totalEmployerCost, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Compute state-based Professional Tax slab.
     */
    public function calculateProfessionalTax(float $gross, int $month, string $state = 'standard'): float
    {
        // Standard Maharashtra / Karnataka slab
        if ($gross <= 7500.0) {
            return 0.0;
        }

        if ($gross <= 10000.0) {
            return 175.0;
        }

        // Standard > 10,000 is 200/month, February is 300 to reach annual 2,500 ceiling
        return $month === 2 ? 300.0 : 200.0;
    }

    /**
     * Estimate monthly TDS under India New Tax Regime (Section 115BAC, FY 2024-26).
     */
    public function calculateEstimatedMonthlyTds(float $grossMonthly): float
    {
        $annualGross = $grossMonthly * 12;
        $netTaxable = max(0.0, $annualGross - self::ANNUAL_STANDARD_DEDUCTION);

        // Section 87A rebate applies if net taxable income <= ₹7,00,000 (Tax liability becomes Nil)
        if ($netTaxable <= 700000.0) {
            return 0.0;
        }

        // Slab computation:
        // 0 to 3L: Nil
        // 3L to 7L (400,000 @ 5%): 20,000
        // 7L to 10L (300,000 @ 10%): 30,000
        // 10L to 12L (200,000 @ 15%): 30,000
        // 12L to 15L (300,000 @ 20%): 60,000
        // Above 15L: 30%
        $tax = 0.0;

        if ($netTaxable > 300000.0) {
            $taxableInSlab = min($netTaxable, 700000.0) - 300000.0;
            $tax += $taxableInSlab * 0.05;
        }

        if ($netTaxable > 700000.0) {
            $taxableInSlab = min($netTaxable, 1000000.0) - 700000.0;
            $tax += $taxableInSlab * 0.10;
        }

        if ($netTaxable > 1000000.0) {
            $taxableInSlab = min($netTaxable, 1200000.0) - 1000000.0;
            $tax += $taxableInSlab * 0.15;
        }

        if ($netTaxable > 1200000.0) {
            $taxableInSlab = min($netTaxable, 1500000.0) - 1200000.0;
            $tax += $taxableInSlab * 0.20;
        }

        if ($netTaxable > 1500000.0) {
            $tax += ($netTaxable - 1500000.0) * 0.30;
        }

        // Add 4% Health & Education Cess
        $totalTaxWithCess = $tax * 1.04;

        return round($totalTaxWithCess / 12, 2);
    }
}
