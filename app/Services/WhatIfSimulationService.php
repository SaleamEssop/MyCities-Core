<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;

/**
 * WhatIfSimulationService - Stateless Hypothesis Engine
 * 
 * This service handles "What If" scenarios for billing calculations
 * WITHOUT persisting any data to the database. It is purely computational
 * and returns calculated results for preview/estimation purposes.
 * 
 * BLOCK-DAY MODEL RULES apply here as well:
 * - Inclusive Math: Days = (End - Start) + 1
 * - Baton Pass: Opening Reading inheritance
 * - Source of Truth: LPD = Usage / BlockCount
 */
class WhatIfSimulationService
{
    /**
     * SAST Date Normalization Helper
     * All dates must be normalized via this helper
     */
    private function asDate($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->startOfDay()->tz('Africa/Johannesburg');
        }
        return Carbon::parse($date, 'Africa/Johannesburg')->startOfDay();
    }

    /**
     * BLOCK-DAY MODEL: Inclusive Block Counting
     * Days = (End - Start) + 1
     */
    private function getBlockDays($start, $end): int
    {
        $startDate = $this->asDate($start);
        $endDate = $this->asDate($end);
        return (int) $startDate->diffInDays($endDate) + 1;
    }

    /**
     * Calculate tiered cost for given consumption
     * BLOCK-DAY MODEL: Units are in Liters, rates are per kL
     * 
     * @param float $totalUnits - Total consumption in LITERS
     * @param array $tariffs - Array of tariff tiers (min_units/max_units in kL)
     * @return float Total calculated cost
     */
    public function calculateTierCost(float $totalUnits, array $tariffs): float
    {
        $totalCost = 0.0;
        
        // IF: We have tariff tiers to process
        // THEN: Calculate cost for each tier based on consumption
        foreach ($tariffs as $tier) {
            $tierMinKL = ($tier['min_units'] ?? 0);  // Already in kL from extractTiers
            $tierMaxKL = ($tier['max_units'] ?? PHP_INT_MAX);  // Already in kL
            
            // Convert consumption to kL for comparison
            $totalUnitsKL = $totalUnits / 1000;
            
            // IF: Consumption is within this tier's range
            // THEN: Calculate units in tier and apply rate
            if ($totalUnitsKL > $tierMinKL) {
                $unitsInTierKL = min($totalUnitsKL, $tierMaxKL) - $tierMinKL;
                
                // IF: We have positive units in this tier (in kL)
                // THEN: Add tier cost to total
                if ($unitsInTierKL > 0) {
                    $ratePerUnit = $tier['rate_per_unit'] ?? 0;
                    // PHYSICS: Convert kL to cost: (kL / 1000 = still kL) * rate = cost
                    // Since unitsInTierKL is already in kL, multiply directly
                    $totalCost += $unitsInTierKL * $ratePerUnit;
                }
            }
        }
        
        return $totalCost;
    }

    /**
     * Calculate consumption between two readings (Period to Period)
     * 
     * @param array $readings - Array of readings with 'date' and 'value'
     * @param int $digitCount - Meter digit count for rollover calculation
     * @return array Result with usage, rate, and block count
     */
    public function calculatePeriodToPeriod(array $readings, int $digitCount = 4): array
    {
        // IF: We have fewer than 2 readings
        // THEN: Cannot calculate period-to-period usage
        if (count($readings) < 2) {
            return [
                'usage' => 0,
                'rate_per_day' => 0,
                'blocks' => 0,
                'status' => 'INSUFFICIENT_READINGS'
            ];
        }

        $r1 = $readings[0];
        $r2 = $readings[1];

        // Normalize readings to integers (Math Sanity)
        $r1Value = floor((float) $r1['value']);
        $r2Value = floor((float) $r2['value']);

        $d1 = $this->asDate($r1['date']);
        $d2 = $this->asDate($r2['date']);

        // Calculate block days using inclusive math
        $blocks = $this->getBlockDays($d1, $d2);
        $blocks = max(1, $blocks); // Prevent division by zero

        // Calculate usage (handle meter rollover)
        $usage = $r2Value - $r1Value;
        
        // IF: Usage is negative (meter rollover occurred)
        // THEN: Calculate rollover usage
        if ($usage < 0) {
            $rolloverLimit = pow(10, $digitCount);
            $usage = ($rolloverLimit - $r1Value) + $r2Value;
        }

        // Calculate rate per day (LPD)
        $ratePerDay = $usage / $blocks;

        return [
            'usage' => $usage,
            'rate_per_day' => $ratePerDay,
            'blocks' => $blocks,
            'status' => 'CALCULATED'
        ];
    }

    /**
     * Calculate consumption for a date range
     * 
     * @param string $startDate - Period start date
     * @param string $endDate - Period end date
     * @param array $readings - Array of readings
     * @param int $digitCount - Meter digit count
     * @param float $seedRate - Fallback rate for gaps (optional)
     * @return array Calculated usage and metadata
     */
    public function calculateDateToDate(
        string $startDate, 
        string $endDate, 
        array $readings, 
        int $digitCount = 4,
        float $seedRate = 0
    ): array {
        $pStart = $this->asDate($startDate);
        $pEnd = $this->asDate($endDate);

        // Generate sectors from readings
        $sectors = $this->generateSectors($readings, $digitCount);

        // Distribute usage across the period
        return $this->distribute($pStart, $pEnd, $sectors, $seedRate);
    }

    /**
     * Calculate full bill with all charges
     * 
     * @param string $startDate - Billing period start
     * @param string $endDate - Billing period end
     * @param array $readings - Meter readings
     * @param array $tariff - Tariff template with tiers
     * @param int $digitCount - Meter digit count
     * @param float $openingReading - Opening reading (optional)
     * @return array Complete bill calculation
     */
    public function calculateBill(
        string $startDate,
        string $endDate,
        array $readings,
        array $tariff,
        int $digitCount = 4,
        ?float $openingReading = null
    ): array {
        // Calculate usage for the period
        $usageResult = $this->calculateDateToDate($startDate, $endDate, $readings, $digitCount);
        
        $consumption = $usageResult['usage'];
        $dailyUsage = $usageResult['rate'];
        $blocks = $usageResult['blocks'];

        // Extract water tiers from tariff
        $tiers = $this->extractTiers($tariff);

        // Calculate tiered cost
        $tieredCharge = $this->calculateTierCost($consumption, $tiers);

        // Calculate water out (sewerage) charges
        $waterOutCharge = $this->calculateWaterOutCharges($consumption, $tariff);

        // Calculate closing reading
        // IF: We have an opening reading
        // THEN: Calculate closing = opening + usage
        $calculatedClosing = null;
        if ($openingReading !== null) {
            $calculatedClosing = floor((float) $openingReading + $consumption);
        }

        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'blocks' => $blocks,
            'consumption' => $consumption,
            'daily_usage' => $dailyUsage,
            'opening_reading' => $openingReading,
            'calculated_closing' => $calculatedClosing,
            'tiered_charge' => $tieredCharge,
            'water_out_charge' => $waterOutCharge,
            'total_charge' => $tieredCharge + $waterOutCharge,
            'status' => $usageResult['status']
        ];
    }

    /**
     * Calculate water out (sewerage) charges
     * BLOCK-DAY MODEL: Sewerage is billed as percentage of water consumption
     * 
     * @param float $consumption - Water consumption in LITERS
     * @param array $tariff - Tariff template
     * @return float Sewerage charges
     */
    public function calculateWaterOutCharges(float $consumption, array $tariff): float
    {
        // IF: Tariff has water_out percentage defined
        // THEN: Calculate sewerage as percentage of consumption
        $waterOutPercentage = $tariff['water_out'] ?? 0;
        
        // IF: Water out percentage is set (> 0)
        // THEN: Calculate sewerage charges
        if ($waterOutPercentage > 0) {
            // PHYSICS STEP 1: Calculate sewerage volume (Liters)
            // Example: 10000L * 70% = 7000L sewerage
            $sewerageVolumeLiters = $consumption * ($waterOutPercentage / 100);
            
            // PHYSICS STEP 2: Get rate per kL from tariff
            $sewerageRatePerKL = $tariff['sewerage_rate'] ?? 0;
            
            // PHYSICS STEP 3: Convert to kL and calculate cost
            // Formula: (Liters / 1000) * Rate_per_kL = Cost
            $sewerageVolumeKL = $sewerageVolumeLiters / 1000;
            return $sewerageVolumeKL * $sewerageRatePerKL;
        }

        // IF: No water_out percentage
        // THEN: No sewerage charge
        return 0.0;
    }

    /**
     * Extract tiers from tariff structure
     * BLOCK-DAY MODEL: Tariff stores water_in as Liters, we normalize to kL
     * 
     * @param array $tariff - Tariff template
     * @return array Normalized tiers (min_units/max_units in kL)
     */
    private function extractTiers(array $tariff): array
    {
        $tiers = [];
        
        // IF: Tariff has water_in (JSON structure)
        // THEN: Parse and normalize tiers
        if (isset($tariff['water_in'])) {
            $waterIn = $tariff['water_in'];
            
            // IF: water_in is a string (JSON from DB)
            // THEN: Decode it to array
            if (is_string($waterIn)) {
                $waterIn = json_decode($waterIn, true);
            }

            // IF: We have valid tier data after parsing
            // THEN: Normalize to standard format (kL)
            if (is_array($waterIn)) {
                foreach ($waterIn as $tier) {
                    // PHYSICS: Convert Liters → kL (divide by 1000)
                    $tiers[] = [
                        'min_units' => ($tier['min'] ?? 0) / 1000, // Convert liters to kL
                        'max_units' => isset($tier['max']) && $tier['max'] !== null
                            ? ($tier['max'] / 1000)  // Convert liters to kL
                            : PHP_INT_MAX,  // Infinity (no upper limit)
                        'rate_per_unit' => (float) ($tier['cost'] ?? 0)  // Already per kL
                    ];
                }
            }
        }

        // IF: No water_in tiers found in tariff
        // THEN: Return empty array (caller should handle gracefully)
        return $tiers;
    }

    /**
     * Generate sectors from readings
     * BLOCK-DAY MODEL: Each sector represents a measurement period
     * STRATEGY: Store raw values, never divide until final distribution step
     * 
     * @param array $readings - Array of readings with 'date' and 'value'
     * @param int $digitCount - Meter digit count for rollover detection
     * @return array Sectors with raw usage and block counts
     */
    private function generateSectors(array $readings, int $digitCount): array
    {
        $sectors = [];
        $rolloverLimit = pow(10, $digitCount);

        // IF: We have fewer than 2 readings
        // THEN: Cannot generate sectors (need at least a start and end reading)
        if (count($readings) < 2) {
            return $sectors;
        }

        // Iterate through reading pairs to create sectors
        for ($i = 0; $i < count($readings) - 1; $i++) {
            $r1 = $readings[$i];
            $r2 = $readings[$i + 1];

            // MATH SANITY: Floor readings to integers
            $r1Value = floor((float) $r1['value']);
            $r2Value = floor((float) $r2['value']);

            $d1 = $this->asDate($r1['date']);
            $d2 = $this->asDate($r2['date']);

            // PHYSICS: Calculate block count using inclusive math
            $sectorBlocks = max(1, $this->getBlockDays($d1, $d2));
            
            // Calculate raw usage between readings
            $totalUsage = $r2Value - $r1Value;

            // IF: Usage is negative (meter rolled over from max back to 0)
            // THEN: Calculate rollover-adjusted usage
            // Example: 9999 → 0001 = 2 units (not -9998)
            if ($totalUsage < 0) {
                $totalUsage = ($rolloverLimit - $r1Value) + $r2Value;
            }

            // STRATEGY: Store raw values, not calculated rate
            $sectors[] = [
                'start' => $d1,
                'end' => $d2,
                'start_reading' => $r1Value,
                'end_reading' => $r2Value,
                'total_usage' => $totalUsage,
                'sector_blocks' => $sectorBlocks
            ];
        }

        return $sectors;
    }

    /**
     * Distribute usage across billing period
     * BLOCK-DAY MODEL: RATIO APPORTIONMENT for boundary-spanning sectors
     * 
     * STRATEGY: Never divide usage until the final step
     * PHYSICS: usage = total_sector_usage * (overlap_blocks / sector_blocks)
     * This ensures the simulation "heals" gaps exactly like the live calculator.
     * 
     * @param Carbon $periodStart - Start of billing period
     * @param Carbon $periodEnd - End of billing period
     * @param array $sectors - Usage sectors (each has total_usage and sector_blocks)
     * @param float $seedRate - Fallback rate for gaps (Liters Per Day)
     * @return array Usage result
     */
    private function distribute(Carbon $periodStart, Carbon $periodEnd, array $sectors, float $seedRate = 0): array
    {
        $totalUsage = 0;
        $lastKnownRate = $seedRate;
        $lastBlockDate = null;

        // IF: We have sectors to distribute
        // THEN: Calculate overlapping usage using RATIO APPORTIONMENT
        foreach ($sectors as $sector) {
            $sStart = $sector['start'];
            $sEnd = $sector['end'];
            $sectorBlocks = $sector['sector_blocks'] ?? 1;
            $sectorUsage = $sector['total_usage'] ?? 0;

            // Find overlapping blocks between sector and billing period
            $overlapStart = $sStart->max($periodStart);
            $overlapEnd = $sEnd->min($periodEnd);

            // RATIO APPORTIONMENT: Calculate usage for overlapping portion
            if ($overlapStart->lte($overlapEnd)) {
                $overlapBlocks = $this->getBlockDays($overlapStart, $overlapEnd);
                
                // PHYSICS: Ratio Method - never divide until final step
                // usage = total_usage * (overlap_blocks / total_blocks)
                $ratio = $overlapBlocks / $sectorBlocks;
                $overlapUsage = $sectorUsage * $ratio;
                $totalUsage += $overlapUsage;

                $lastBlockDate = $overlapEnd;
                // Calculate rate from raw values
                $lastKnownRate = $sectorUsage / $sectorBlocks;
            }
            // IF: Sector ends before period starts (legacy/previous sector)
            // THEN: Track its rate for momentum projection
            elseif ($sEnd->lte($periodStart)) {
                $lastKnownRate = $sectorUsage / $sectorBlocks;
            }
        }

        // Momentum Projection: Fill gaps at the end of the period
        // IF: We have a last known rate and period extends beyond last sector
        // THEN: Project usage for remaining blocks using momentum
        if ($lastBlockDate === null || $lastBlockDate->lt($periodEnd)) {
            $gapStart = ($lastBlockDate !== null) 
                ? $lastBlockDate->copy()->addDay() 
                : $periodStart;

            // IF: There's a gap to fill
            // THEN: Add projected usage based on momentum
            if ($gapStart->lte($periodEnd)) {
                $gapBlocks = $this->getBlockDays($gapStart, $periodEnd);
                $totalUsage += $lastKnownRate * $gapBlocks;
            }
        }

        // Determine status based on available data
        // IF: We have sectors with overlap
        // THEN: Status is CALCULATED (exact physics)
        // ELSE IF: We have a seed rate
        // THEN: Status is PROVISIONAL (projected)
        // ELSE: Status is ESTIMATE (no data)
        $hasSectors = !empty(array_filter($sectors, function($s) use ($periodStart, $periodEnd) {
            return $s['start']->lte($periodEnd) && $s['end']->gte($periodStart);
        }));

        $status = $hasSectors ? 'CALCULATED' : ($seedRate > 0 ? 'PROVISIONAL' : 'ESTIMATE');

        // Calculate total blocks in period (inclusive math)
        $totalBlocks = $this->getBlockDays($periodStart, $periodEnd);

        return [
            'usage' => round($totalUsage, 0),
            'rate' => $lastKnownRate,
            'blocks' => $totalBlocks,
            'status' => $status
        ];
    }
}
