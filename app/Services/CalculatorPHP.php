<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Validation\ValidationException;
use App\Models\RegionsAccountTypeCost;

/**
 * CalculatorPHP - Unified Block-Day Model
 * ============================================================
 * BLOCK-DAY MODEL MANDATORY RULES:
 * ============================================================
 * 1. Container Rule: A date is a discrete 24-hour block.
 * 2. Inclusive Math: Days = (End - Start) + 1. 
 * Example: 1st Jan to 10th Jan = 10 Blocks (Days).
 * 3. Baton Pass: Closing Reading of Period A = Opening of Period B.
 * 4. Source of Truth: All daily rates (LPD) = Usage / BlockCount.
 * ============================================================
 */
class CalculatorPHP
{
    /**
     * The Single Source of Truth for Billing Calculation.
     * Block-Day Model: Implements immutability, adjustments, and tariff history.
     * 
     * STRADDLE & SEQUENTIAL GATE PROTOCOL (Section 0.5):
     * - Period N cannot calculate until Period N-1 is reconciled
     * - Sectors spanning period boundaries are split into sub-sectors
     * - Recursive healing ensures baton pass integrity
     */
    public function computePeriod(int $billId)
    {
        return DB::transaction(function () use ($billId) {
            // A. DATA INGESTION
            $bill = $this->loadBill($billId);
            $meterId = $bill->meter_id;
            $meter = $this->loadMeter($meterId);
            $readings = $this->loadAllReadingsForMeter($meterId);

            // B. VALIDATION
            $this->validatePrerequisites($bill, $readings, $meter);

            // B.0 SEQUENTIAL GATE: Check if previous period is reconciled
            // RULE: Period N cannot calculate until Period N-1 has calculated_closing
            $gateResult = $this->enforceSequentialGate($bill, $readings, $meter);
            if ($gateResult['blocked']) {
                return [
                    'success' => false,
                    'status' => 'BLOCKED_BY_SEQUENTIAL_GATE',
                    'message' => $gateResult['message'],
                    'requires_healing' => $gateResult['requires_healing'],
                    'unreconciled_periods' => $gateResult['unreconciled_periods']
                ];
            }

            // B.1 IMMUTABILITY CHECK: Cannot recalculate finalized/invoiced bills
            // This protects audit integrity - invoices must never change
            if (in_array($bill->status, ['FINALIZED', 'INVOICED'])) {
                // Only allow updating calculated_closing for healing purposes
                // NEVER update provisional_closing or consumption on finalized bills
                return $this->healFinalizedBill($bill, $readings, $meter);
            }

            // C. SECTOR GENERATION
            // Logic: Create historical usage "sectors" based on Block-Day inclusive counts.
            $sectors = ($readings->count() >= 2) ? $this->generateSectors($readings, $meter) : [];

            // D. ADJUSTMENT CALCULATION (Immutability Law)
            $adjustments = $this->calculateAdjustmentsForPastPeriods(
                $bill->account_id,
                $meterId,
                $billId,
                $sectors,
                $bill->period_start_date
            );

            // E. MOMENTUM SEED
            $prevBill = DB::table('bills')
                ->where('meter_id', $meterId)
                ->where('id', '<', $billId)
                ->orderBy('id', 'desc')
                ->first();

            $seed = ($readings->count() >= 2 && $prevBill) ? ($prevBill->daily_usage ?? 0) : 0;

            // F. CALCULATION PIPELINE (The Distribution)
            $calcResult = $this->distribute(
                $bill->period_start_date,
                $bill->period_end_date,
                $sectors,
                $seed,
                $meter
            );
            $finalUsage = $calcResult['usage'];
            $currentDailyUsage = $calcResult['rate'];

            // G. BLOCK-DAY STATUS DETECTION
            $hasBlockDayTruth = false;
            $pStart = $this->asDate($bill->period_start_date);
            $pEnd = $this->asDate($bill->period_end_date);

            foreach ($sectors as $sector) {
                // Check if any sector overlaps the current billing block range
                if ($sector['start']->lte($pEnd) && $sector['end']->gte($pStart)) {
                    $hasBlockDayTruth = true;
                    break;
                }
            }

            // H. STATUS DETERMINATION
            $status = 'CALCULATED';
            if (!$hasBlockDayTruth && $seed == 0 && $readings->count() < 2) {
                $status = 'UNABLE_TO_CALCULATE';
                $finalUsage = 0;
            } elseif (!$hasBlockDayTruth && $seed > 0) {
                $status = 'PROVISIONAL';
            } elseif (!$hasBlockDayTruth) {
                $status = 'SILENCE';
                $finalUsage = 0;
            }

            // I. METRICS & PERSISTENCE
            $daysInPeriod = $this->getBlockDays($bill->period_start_date, $bill->period_end_date);
            
            if ($readings->count() < 2) {
                $currentDailyUsage = 0;
            }

            $openingReading = $this->getOpeningReading($bill, $readings, $meter);
            $currentTariff = $this->getTariffForPeriod($bill->tariff_template_id, $bill->period_start_date);
            $tariffs = $this->loadTariffsForBill($currentTariff);
            $tieredCharge = $this->calculateTieredCost($finalUsage, $tariffs);

            // J. ADJUSTMENTS
            $adjustmentCharge = 0;
            foreach ($adjustments as $adjustment) {
                $adjustmentCharge += $adjustment['charge'];
            }

            $updateData = [
                'consumption' => (int) $finalUsage,
                'daily_usage' => $currentDailyUsage,
                'tiered_charge' => $tieredCharge,
                'adjustment_charge' => $adjustmentCharge,
                'is_provisional' => ($status !== 'CALCULATED'),
                'status' => $status,
                'updated_at' => now()
            ];

            // K. BATON PASS (Calculated Closing)
            $calculatedClosing = null;
            if ($hasBlockDayTruth) {
                $calculatedClosing = round($openingReading + $finalUsage, 0);
                $updateData['calculated_closing'] = $calculatedClosing;
            }

            DB::table('bills')->where('id', $billId)->update($updateData);

            return [
                'success' => true,
                'status' => $status,
                'consumption' => (int) $finalUsage,
                'daily_usage' => $currentDailyUsage,
                'tiered_charge' => $tieredCharge,
                'opening_reading' => (int) $openingReading,
                'calculated_closing' => $calculatedClosing,
                'adjustments' => $adjustments
            ];
        });
    }

    /**
     * Block-Day Model: SAST Date Normalization
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
     * Block-Day Model: Generate Sectors
     * STRATEGY: Store raw values, never divide until final distribution step
     * 
     * IF: We have at least 2 readings (a reading pair)
     * THEN: Create a sector representing that measurement period
     */
    private function generateSectors($readings, $meter)
    {
        $sectors = [];
        $digitCount = $meter->digit_count ?? 4;
        $rolloverLimit = pow(10, $digitCount);

        for ($i = 0; $i < count($readings) - 1; $i++) {
            $r1 = $readings[$i];
            $r2 = $readings[$i + 1];

            // Normalize to integers (Math Sanity Rule)
            $r1Value = floor((float) $r1->reading_value);
            $r2Value = floor((float) $r2->reading_value);

            $d1 = $this->asDate($r1->reading_date);
            $d2 = $this->asDate($r2->reading_date);

            // IF: We have a valid date range
            // THEN: Calculate block count using inclusive math
            $sectorBlocks = max(1, $this->getBlockDays($d1, $d2));
            
            // Calculate raw usage between readings
            $totalUsage = $r2Value - $r1Value;

            // IF: Usage is negative (meter rolled over from max back to 0)
            // THEN: Calculate rollover-adjusted usage
            if ($totalUsage < 0) {
                $totalUsage = ($rolloverLimit - $r1Value) + $r2Value;
            }

            // STRATEGY: Store raw values, not calculated rate
            // Include start_reading and end_reading for Gatepost Protocol healing
            $sectors[] = [
                'start' => $d1,
                'end' => $d2,
                'start_reading' => $r1Value,    // Gatepost start
                'end_reading' => $r2Value,      // Gatepost end
                'total_usage' => $totalUsage,
                'sector_blocks' => $sectorBlocks
            ];
        }
        return $sectors;
    }

    /**
     * Block-Day Model: Distribute usage across blocks
     * RATIO APPORTIONMENT: usage = total_sector_usage * (overlap_blocks / sector_blocks)
     * 
     * WEIGHTED PROJECTION LAW (Section 0.5(C)):
     * Projections use the Weighted Mean of the current period (Usage_total / Blocks_total)
     * rather than the instantaneous rate of the last sector.
     * This prevents end-of-month spikes from skewing the projection.
     */
    private function distribute($periodStart, $periodEnd, $sectors, $dailyUsageSeed = 0, $meter = null)
    {
        $totalUsage = 0;
        $pStart = $this->asDate($periodStart);
        $pEnd = $this->asDate($periodEnd);

        $lastBlockDate = null;
        $lastKnownRate = $dailyUsageSeed;
        
        // WEIGHTED PROJECTION: Track total usage and blocks for weighted average
        $totalSectorUsage = 0;
        $totalSectorBlocks = 0;

        foreach ($sectors as $sector) {
            $sStart = $sector['start'];
            $sEnd = $sector['end'];
            $sectorBlocks = $sector['sector_blocks'] ?? 1;
            $sectorUsage = $sector['total_usage'] ?? 0;

            // Find overlapping blocks between sector and billing period
            $overlapStart = $sStart->max($pStart);
            $overlapEnd = $sEnd->min($pEnd);

            // RATIO APPORTIONMENT: Calculate usage for overlapping portion
            if ($overlapStart->lte($overlapEnd)) {
                $overlapBlocks = $this->getBlockDays($overlapStart, $overlapEnd);
                
                // PHYSICS: Ratio Method - never divide until final step
                // usage = total_usage * (overlap_blocks / total_blocks)
                $ratio = $overlapBlocks / $sectorBlocks;
                $overlapUsage = $sectorUsage * $ratio;
                $totalUsage += $overlapUsage;

                $lastBlockDate = $overlapEnd;
                
                // WEIGHTED PROJECTION: Accumulate for weighted average
                $totalSectorUsage += $sectorUsage;
                $totalSectorBlocks += $sectorBlocks;
                
                // Calculate rate from raw values (fallback)
                $lastKnownRate = $sectorUsage / $sectorBlocks;
            }
            // IF: Sector ends before period starts (legacy sector)
            // THEN: Track its rate for momentum
            elseif ($sEnd->lte($pStart)) {
                $lastKnownRate = $sectorUsage / $sectorBlocks;
            }
        }

        // WEIGHTED PROJECTION LAW: Use Period-to-Date Average instead of lastKnownRate
        // Formula: Weighted Rate = Total Usage / Total Blocks
        // This prevents end-of-month spikes from skewing the projection
        $weightedRate = ($totalSectorBlocks > 0) 
            ? ($totalSectorUsage / $totalSectorBlocks) 
            : $lastKnownRate;

        // Momentum Projection: Fill gaps at the end of the period
        if ($lastBlockDate === null || $lastBlockDate->lt($pEnd)) {
            $gapStart = ($lastBlockDate !== null) ? $lastBlockDate->copy()->addDay() : $pStart;

            if ($gapStart->lte($pEnd)) {
                $gapBlocks = $this->getBlockDays($gapStart, $pEnd);
                // WEIGHTED PROJECTION: Use weighted rate, not instantaneous rate
                $totalUsage += $weightedRate * $gapBlocks;
            }
        }

        return [
            'usage' => round($totalUsage, 0),
            'rate' => $weightedRate // Return weighted rate for consistency
        ];
    }

    /**
     * Block-Day Model: THE HEALED BATON
     * Prioritizes "Measured Truth" over stored calculated_closing
     * 
     * IF: A previous bill exists
     *    THEN: Search for a Reading Sector that overlaps the previous bill's end date
     *    IF: Found, calculate "Healed Closing" using Ratio Apportionment
     *    THEN: USE this "Healed" value as current bill's opening_reading
     *    ELSE: Fallback to stored calculated_closing
     * 
     * ELSE IF: This is the first bill but readings exist
     * THEN: Use the first reading as the opening
     * 
     * ELSE: Use the bill's declared opening_reading (fallback)
     */
    private function getOpeningReading($bill, $readings, $meter = null)
    {
        $prevBill = DB::table('bills')
            ->where('meter_id', $bill->meter_id)
            ->where('id', '<', $bill->id)
            ->orderBy('id', 'desc')
            ->first();

        // IF: Previous bill exists
        // THEN: Try to find "Healed Baton" from current readings
        if ($prevBill) {
            // Generate sectors from current readings for healing
            $meterForSectors = is_object($meter) ? $meter : (object) ['digit_count' => 4];
            $sectors = $this->generateSectors($readings, $meterForSectors);
            
            $prevEndDate = $this->asDate($prevBill->period_end_date);
            
            // IF: We have sectors AND a previous bill end date
            // THEN: Search for sector that overlaps previous bill's end
            $healedOpening = null;
            
            foreach ($sectors as $sector) {
                // Check if this sector overlaps with previous bill's end
                // We need a sector that spans INTO the current period from before
                $sectorStart = $sector['start'];
                $sectorEnd = $sector['end'];
                
                // IF: Sector starts before or at previous bill end AND ends after it
                // THEN: This sector can provide the "Healed" reading
                if ($sectorStart->lte($prevEndDate) && $sectorEnd->gte($prevEndDate)) {
                    // Calculate how much of this sector extends into current period
                    // Ratio: (sector_end - prev_end_date + 1) / total_sector_blocks
                    // But we need actual reading value at the boundary
                    
                    // Use the reading at the end of sector as our "healed" closing
                    // The sector end reading IS the healed value
                    $healedOpening = floor((float) $sector['end_reading'] ?? 0);
                    break;
                }
            }
            
            // IF: Found healed opening from overlapping sector
            // THEN: Use it (This is the Measured Truth)
            if ($healedOpening !== null) {
                return $healedOpening;
            }
            
            // ELSE: Fallback to stored calculated_closing
            if (isset($prevBill->calculated_closing)) {
                return floor((float) $prevBill->calculated_closing);
            }
        }

        // IF: No previous bill but readings exist
        // THEN: Use first reading as anchor
        if (!$prevBill && $readings->count() > 0) {
            return floor((float) $readings->first()->reading_value);
        }

        // ELSE: Fallback to declared opening reading
        return floor((float) ($bill->opening_reading ?? 0));
    }

    // ============================================================================
    // SECTION 0.5: STRADDLE & SEQUENTIAL GATE PROTOCOL
    // ============================================================================

    /**
     * SEQUENTIAL GATE: Enforce Period N-1 reconciliation before Period N
     * 
     * RULE: Period N cannot calculate final usage until Period N-1 is Reconciled.
     * Definition of Reconciled: The opening_reading of Period N must equal the 
     * calculated_closing of Period N-1.
     * 
     * GATE LOCK (Section 0.5):
     * If triggerHealingChain fails, the system MUST throw a HardStopException.
     * It should NEVER "fall through" and allow a provisional calculation if a 
     * previous period is healable but failed to heal.
     * 
     * @param object $bill Current bill being calculated
     * @param collection $readings All readings for the meter
     * @param object $meter Meter object
     * @return array ['blocked' => bool, 'message' => string, 'requires_healing' => array]
     * @throws \Exception HardStopException if healing fails
     */
    private function enforceSequentialGate($bill, $readings, $meter)
    {
        // Find all previous bills for this meter
        $previousBills = DB::table('bills')
            ->where('meter_id', $bill->meter_id)
            ->where('id', '<', $bill->id)
            ->orderBy('id', 'asc')
            ->get();

        // If no previous bills, gate is open (this is Period 1)
        if ($previousBills->isEmpty()) {
            return [
                'blocked' => false,
                'message' => 'Period 1 - no previous period to reconcile',
                'requires_healing' => [],
                'unreconciled_periods' => []
            ];
        }

        // Check each previous bill for reconciliation status
        $unreconciledPeriods = [];
        $requiresHealing = [];

        foreach ($previousBills as $prevBill) {
            // A period is reconciled if it has a calculated_closing
            // OR if it's the immediate previous period and we have readings that can heal it
            $isReconciled = !is_null($prevBill->calculated_closing);

            if (!$isReconciled) {
                $unreconciledPeriods[] = [
                    'bill_id' => $prevBill->id,
                    'period_start' => $prevBill->period_start_date,
                    'period_end' => $prevBill->period_end_date,
                    'status' => $prevBill->status
                ];

                // Check if we can heal this period from current readings
                if ($readings->count() >= 2) {
                    $canHeal = $this->canHealPeriod($prevBill, $readings, $meter);
                    if ($canHeal) {
                        $requiresHealing[] = $prevBill->id;
                    }
                }
            }
        }

        // If there are unreconciled periods, block the calculation
        if (!empty($unreconciledPeriods)) {
            // If we can heal, trigger healing
            if (!empty($requiresHealing)) {
                $healingResult = $this->triggerHealingChain($requiresHealing, $readings, $meter);
                if ($healingResult['success']) {
                    // Healing succeeded, allow calculation to proceed
                    return [
                        'blocked' => false,
                        'message' => 'Healing completed for ' . count($requiresHealing) . ' period(s)',
                        'requires_healing' => [],
                        'unreconciled_periods' => []
                    ];
                }
                
                // GATE LOCK: Healing failed - throw HardStopException
                // NEVER fall through to allow provisional calculation
                $errorDetails = implode('; ', array_map(function($e) {
                    return "Bill #{$e['bill_id']}: {$e['error']}";
                }, $healingResult['errors']));
                
                throw new Exception(
                    "HARD STOP: Healing failed for " . count($healingResult['errors']) . 
                    " period(s). Cannot proceed with calculation. Errors: {$errorDetails}"
                );
            }

            // Cannot heal - block the calculation
            $periodList = implode(', ', array_map(function($p) {
                return "Period {$p['period_start']} to {$p['period_end']}";
            }, $unreconciledPeriods));

            return [
                'blocked' => true,
                'message' => "Cannot calculate: Previous period(s) not reconciled. {$periodList}",
                'requires_healing' => $requiresHealing,
                'unreconciled_periods' => $unreconciledPeriods
            ];
        }

        // All previous periods are reconciled
        return [
            'blocked' => false,
            'message' => 'All previous periods reconciled',
            'requires_healing' => [],
            'unreconciled_periods' => []
        ];
    }

    /**
     * Check if a period can be healed from current readings
     * 
     * A period can be healed if there's a reading sector that spans across
     * the period's end date.
     */
    private function canHealPeriod($bill, $readings, $meter)
    {
        if ($readings->count() < 2) {
            return false;
        }

        $sectors = $this->generateSectors($readings, $meter);
        $periodEnd = $this->asDate($bill->period_end_date);

        foreach ($sectors as $sector) {
            // Check if sector spans across this period's end
            if ($sector['start']->lte($periodEnd) && $sector['end']->gt($periodEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * TRIGGER HEALING CHAIN: Recursively heal all unreconciled periods
     * 
     * No Multi-Step Jumps: If the user adds a reading in May, and the last 
     * reading was in January, heal February, March, and April in a recursive 
     * loop before May can be calculated.
     */
    private function triggerHealingChain(array $billIds, $readings, $meter)
    {
        $healedCount = 0;
        $errors = [];

        // Sort bill IDs in ascending order (heal oldest first)
        sort($billIds);

        foreach ($billIds as $billId) {
            $bill = $this->loadBill($billId);
            $healResult = $this->healPeriod($bill, $readings, $meter);

            if ($healResult['success']) {
                $healedCount++;
            } else {
                $errors[] = [
                    'bill_id' => $billId,
                    'error' => $healResult['message'] ?? 'Unknown error'
                ];
            }
        }

        return [
            'success' => $healedCount === count($billIds),
            'healed_count' => $healedCount,
            'errors' => $errors
        ];
    }

    /**
     * HEAL PERIOD: Calculate calculated_closing for a period from future readings
     * 
     * Uses Ratio Apportionment to determine what the closing reading should have been
     * based on a sector that spans across the period's end date.
     */
    private function healPeriod($bill, $readings, $meter)
    {
        $sectors = ($readings->count() >= 2) ? $this->generateSectors($readings, $meter) : [];
        
        if (empty($sectors)) {
            return [
                'success' => false,
                'message' => 'No sectors available for healing'
            ];
        }

        $periodEnd = $this->asDate($bill->period_end_date);
        $healedClosing = null;
        $healingSector = null;

        // Find sector that spans across this period's end
        foreach ($sectors as $sector) {
            if ($sector['start']->lte($periodEnd) && $sector['end']->gt($periodEnd)) {
                $healingSector = $sector;
                break;
            }
        }

        if (!$healingSector) {
            return [
                'success' => false,
                'message' => 'No straddling sector found for healing'
            ];
        }

        // Calculate healed closing using Ratio Apportionment
        // Formula: reading_at_boundary = start_reading + (total_usage * (blocks_to_end / total_blocks))
        $blocksToEnd = $this->getBlockDays($healingSector['start'], $periodEnd);
        $totalBlocks = $healingSector['sector_blocks'];
        $ratio = $blocksToEnd / $totalBlocks;

        $healedClosing = round($healingSector['start_reading'] + ($healingSector['total_usage'] * $ratio), 0);

        // Update the bill's calculated_closing
        DB::table('bills')->where('id', $bill->id)->update([
            'calculated_closing' => $healedClosing,
            'updated_at' => now()
        ]);

        return [
            'success' => true,
            'calculated_closing' => $healedClosing,
            'healing_sector' => [
                'start' => $healingSector['start']->format('Y-m-d'),
                'end' => $healingSector['end']->format('Y-m-d'),
                'ratio' => $ratio
            ]
        ];
    }

    /**
     * STRADDLE SPLIT: Split a sector into sub-sectors at period boundaries
     * 
     * When a reading spans across one or more period boundaries, split the sector
     * into virtual sub-sectors at each boundary using Ratio Apportionment.
     * 
     * INTEGER ANCHOR RULE (Section 0.5(E)):
     * All calculated_closing and consumption values must be stored as Integers.
     * 
     * REMAINDER METHOD:
     * - For sub-sectors 1 to N-1: use floor(Total Usage * Ratio)
     * - For the final sub-sector (N): use Total Sector Usage - sum(Previous Sub-Sectors)
     * 
     * This ensures that the sum of parts always equals the physical whole,
     * with zero decimals and zero "lost" litres.
     * 
     * @param array $sector The sector to split
     * @param array $periodBoundaries Array of period end dates to split at
     * @return array Array of sub-sectors
     */
    private function splitStraddleSector($sector, $periodBoundaries)
    {
        $subSectors = [];
        $sectorStart = $sector['start'];
        $sectorEnd = $sector['end'];
        $sectorBlocks = $sector['sector_blocks'];
        $sectorUsage = (int) $sector['total_usage']; // INTEGER ANCHOR: Ensure integer
        $startReading = (int) $sector['start_reading']; // INTEGER ANCHOR: Ensure integer

        // Sort boundaries chronologically
        $boundaries = collect($periodBoundaries)->sort()->values();

        $currentStart = $sectorStart;
        $currentReading = $startReading;
        $subId = 1;
        $runningTotal = 0; // Track accumulated usage for Remainder Method

        foreach ($boundaries as $boundary) {
            $boundaryDate = $this->asDate($boundary);

            // Only split if boundary is within the sector
            if ($boundaryDate->gt($sectorStart) && $boundaryDate->lt($sectorEnd)) {
                // Calculate sub-sector up to this boundary
                $subBlocks = $this->getBlockDays($currentStart, $boundaryDate);
                $ratio = $subBlocks / $sectorBlocks;
                
                // REMAINDER METHOD: Use floor for sub-sectors 1 to N-1
                // This ensures integer litres with no decimals
                $subUsage = (int) floor($sectorUsage * $ratio);
                $runningTotal += $subUsage;
                
                // INTEGER ANCHOR: All readings are integers
                $subEndReading = $currentReading + $subUsage;

                $subSectors[] = [
                    'sector_id' => $sector['sector_id'] ?? 0,
                    'sub_id' => $subId++,
                    'start' => $currentStart,
                    'end' => $boundaryDate,
                    'start_reading' => $currentReading,  // Integer
                    'end_reading' => $subEndReading,     // Integer
                    'total_usage' => $subUsage,          // Integer (floor)
                    'sector_blocks' => $subBlocks,
                    'is_sub_sector' => true
                ];

                // BATON PASS: Next sub-sector starts EXACTLY where this one ended
                $currentStart = $boundaryDate->copy()->addDay();
                $currentReading = $subEndReading; // Integer baton pass
            }
        }

        // Add remaining segment (from last boundary to sector end)
        // REMAINDER METHOD: Final sub-sector "snaps" to the physical reading
        // Formula: Total Sector Usage - sum(Previous Sub-Sectors)
        if ($currentStart->lt($sectorEnd)) {
            // REMAINDER METHOD: The final piece "snaps" to the physical reading
            // This ensures sum of parts = physical whole, with zero "lost" litres
            $finalSubUsage = $sectorUsage - $runningTotal;

            $subSectors[] = [
                'sector_id' => $sector['sector_id'] ?? 0,
                'sub_id' => $subId,
                'start' => $currentStart,
                'end' => $sectorEnd,
                'start_reading' => $currentReading,  // Integer baton from previous
                'end_reading' => (int) $sector['end_reading'],  // Original sector end (integer)
                'total_usage' => $finalSubUsage,     // Remainder - ensures sum = total
                'sector_blocks' => $this->getBlockDays($currentStart, $sectorEnd),
                'is_sub_sector' => true
            ];
        }

        return $subSectors;
    }

    /**
     * DETECT STRADDLE: Check if any sectors span period boundaries
     * 
     * @param array $sectors Array of sectors to check
     * @param array $periods Array of periods with start/end dates
     * @return array Sectors with straddle information
     */
    private function detectStraddles($sectors, $periods)
    {
        $processedSectors = [];

        foreach ($sectors as $sector) {
            $straddles = [];
            $sectorStart = $sector['start'];
            $sectorEnd = $sector['end'];

            foreach ($periods as $period) {
                $periodEnd = $this->asDate($period['end']);

                // Check if sector spans across this period's end
                if ($sectorStart->lte($periodEnd) && $sectorEnd->gt($periodEnd)) {
                    $straddles[] = $periodEnd->format('Y-m-d');
                }
            }

            if (!empty($straddles)) {
                // Split the sector at each boundary
                $subSectors = $this->splitStraddleSector($sector, $straddles);
                $processedSectors = array_merge($processedSectors, $subSectors);
            } else {
                $processedSectors[] = $sector;
            }
        }

        return $processedSectors;
    }

    // ============================================================================
    // ADDITIONAL METHODS (Simplified for compatibility)
    // ============================================================================

    private function calculateTieredCost($totalUnits, $tariffs)
    {
        $totalCost = 0;
        foreach ($tariffs as $tier) {
            $tierMin = $tier->min_units ?? 0;
            $tierMax = $tier->max_units ?? PHP_INT_MAX;
            if ($totalUnits <= $tierMin)
                continue;

            $unitsInTier = min($totalUnits, $tierMax) - $tierMin;
            if ($unitsInTier > 0) {
                $totalCost += ($unitsInTier / 1000) * $tier->rate_per_unit;
            }
        }
        return $totalCost;
    }

    private function calculateAdjustmentsForPastPeriods($accountId, $meterId, $currentBillId, $sectors, $currentPeriodStart)
    {
        $adjustments = [];

        $pastBills = DB::table('bills')
            ->where('account_id', $accountId)
            ->where('meter_id', $meterId)
            ->where('id', '<', $currentBillId)
            ->where(function ($query) {
                $query->where('is_provisional', true)
                    ->orWhere('status', 'SILENCE')
                    ->orWhere('status', 'UNABLE_TO_CALCULATE');
            })
            ->orderBy('id', 'asc')
            ->get();

        foreach ($pastBills as $pastBill) {
            $blockDayTruth = $this->distribute(
                $pastBill->period_start_date,
                $pastBill->period_end_date,
                $sectors,
                0
            );

            $originalConsumption = (int) ($pastBill->consumption ?? 0);
            $delta = $blockDayTruth - $originalConsumption;

            if (abs($delta) > 0) {
                $pastPeriodTariff = $this->getTariffForPeriod(
                    $pastBill->tariff_template_id,
                    $pastBill->period_start_date
                );
                $pastTariffs = $this->loadTariffsForBill($pastPeriodTariff);
                $deltaCharge = $this->calculateTieredCost(abs($delta), $pastTariffs);

                $adjustments[] = [
                    'past_bill_id' => $pastBill->id,
                    'period_start' => $pastBill->period_start_date,
                    'period_end' => $pastBill->period_end_date,
                    'original_consumption' => $originalConsumption,
                    'block_day_truth' => $blockDayTruth,
                    'delta' => $delta,
                    'charge' => $deltaCharge,
                    'description' => "Adjustment for period {$pastBill->period_start_date} to {$pastBill->period_end_date}"
                ];
            }
        }

        return $adjustments;
    }

    // ============================================================================
    // SECTION 5: DATA HELPERS & VALIDATION
    // ============================================================================

    /**
     * Get tariff for a specific period, handling effective dates
     */
    private function getTariffForPeriod($tariffTemplateId, $periodStartDate)
    {
        // First try to get the specific template
        $tariff = DB::table('regions_account_type_cost')
            ->where('id', $tariffTemplateId)
            ->first();

        if (!$tariff) {
            throw new Exception("Tariff template #{$tariffTemplateId} not found.");
        }

        // If tariff has effective_from, use Persistence Law
        if (isset($tariff->effective_from) && $tariff->effective_from) {
            $periodStart = Carbon::parse($periodStartDate, 'Africa/Johannesburg');
            $effectiveFrom = Carbon::parse($tariff->effective_from, 'Africa/Johannesburg');

            // If this tariff is not effective for the period, find the correct one
            if ($periodStart->lt($effectiveFrom)) {
                $historicalTariff = DB::table('regions_account_type_cost')
                    ->where('region_id', $tariff->region_id)
                    ->where('effective_from', '<=', $periodStartDate)
                    ->where(function ($query) use ($periodStartDate) {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $periodStartDate);
                    })
                    ->orderBy('effective_from', 'desc')
                    ->first();

                if ($historicalTariff) {
                    return $historicalTariff;
                }
            }
        }

        return $tariff;
    }

    /**
     * Load a bill by ID
     */
    private function loadBill($id)
    {
        $bill = DB::table('bills')->find($id);
        if (!$bill)
            throw new Exception("Bill #{$id} not found.");
        return $bill;
    }

    /**
     * Load a meter by ID
     */
    private function loadMeter($meterId)
    {
        $meter = DB::table('meters')->find($meterId);
        if (!$meter)
            throw new Exception("Meter #{$meterId} not found.");
        // Ensure digit_count exists (default 4)
        if (!isset($meter->digit_count)) {
            $meter->digit_count = 4;
        }
        return $meter;
    }

    /**
     * Load all readings for a meter
     */
    private function loadAllReadingsForMeter($meterId)
    {
        return DB::table('meter_readings')->where('meter_id', $meterId)->orderBy('reading_date', 'asc')->get();
    }

    /**
     * Load tariffs for a bill from tariff template
     */
    private function loadTariffsForBill($tariff)
    {
        // PRIMARY: Read from regions_account_type_cost.water_in JSON (source of truth)
        // Note: DB::table() returns JSON as string, need to decode
        $waterIn = null;
        if (isset($tariff->water_in)) {
            if (is_string($tariff->water_in)) {
                $waterIn = json_decode($tariff->water_in, true);
            } elseif (is_array($tariff->water_in)) {
                $waterIn = $tariff->water_in;
            }
        }

        if (is_array($waterIn) && !empty($waterIn)) {
            $tiers = [];
            foreach ($waterIn as $tier) {
                $tiers[] = (object) [
                    'min_units' => ($tier['min'] ?? 0) / 1000,  // Convert Liters to kL
                    'max_units' => isset($tier['max']) && $tier['max'] !== null
                        ? ($tier['max'] / 1000)  // Convert Liters to kL
                        : PHP_INT_MAX,
                    'rate_per_unit' => (float) ($tier['cost'] ?? 0)  // cost → rate_per_unit
                ];
            }
            return collect($tiers);
        }

        // FALLBACK: Try deprecated tariff_tiers table (for backward compatibility)
        $tiers = DB::table('tariff_tiers')
            ->where('tariff_template_id', $tariff->id ?? $tariff->tariff_template_id ?? null)
            ->orderBy('min_units', 'asc')
            ->get();

        return $tiers;
    }

    /**
     * Validate prerequisites for billing calculation
     */
    private function validatePrerequisites($bill, $readings, $meter)
    {
        if ($readings->count() < 1)
            throw ValidationException::withMessages(['readings' => "Min 1 reading required."]);

        $startDate = Carbon::parse($bill->period_start_date, 'Africa/Johannesburg');
        $endDate = Carbon::parse($bill->period_end_date, 'Africa/Johannesburg');
        if ($startDate->gte($endDate))
            throw ValidationException::withMessages(['period' => "Invalid date range."]);
    }

    /**
     * Heal a finalized bill - ONLY updates calculated_closing
     * 
     * IMMUTABILITY RULE: When a bill is FINALIZED or INVOICED:
     * - consumption, daily_usage, tiered_charge, provisional_closing = LOCKED
     * - calculated_closing = Can be updated via healing from future readings
     * 
     * This allows the "Healed Truth" to propagate while protecting audit integrity.
     */
    private function healFinalizedBill($bill, $readings, $meter)
    {
        // Generate sectors from current readings
        $sectors = ($readings->count() >= 2) ? $this->generateSectors($readings, $meter) : [];
        
        $pStart = $this->asDate($bill->period_start_date);
        $pEnd = $this->asDate($bill->period_end_date);
        
        // Find sector that overlaps this bill's end date
        $healedClosing = null;
        
        foreach ($sectors as $sector) {
            $sectorStart = $sector['start'];
            $sectorEnd = $sector['end'];
            
            // IF: Sector spans across this bill's end date
            // THEN: We can calculate the "Healed Truth"
            if ($sectorStart->lte($pEnd) && $sectorEnd->gt($pEnd)) {
                // Use Ratio Apportionment to find the reading at period end
                // Formula: reading_at_boundary = start_reading + (total_usage * (blocks_to_end / total_blocks))
                $blocksToEnd = $this->getBlockDays($sectorStart, $pEnd);
                $totalBlocks = $sector['sector_blocks'];
                $ratio = $blocksToEnd / $totalBlocks;
                
                $startReading = $sector['start_reading'];
                $totalUsage = $sector['total_usage'];
                
                $healedClosing = round($startReading + ($totalUsage * $ratio), 0);
                break;
            }
        }
        
        // IF: We found a healed closing value
        // THEN: Update ONLY the calculated_closing field
        if ($healedClosing !== null) {
            DB::table('bills')->where('id', $bill->id)->update([
                'calculated_closing' => $healedClosing,
                'updated_at' => now()
            ]);
            
            return [
                'success' => true,
                'status' => $bill->status, // Keep original status
                'action' => 'HEALED',
                'message' => 'Calculated closing updated via healing. Provisional closing remains immutable.',
                'provisional_closing' => (int) ($bill->provisional_closing ?? $bill->calculated_closing ?? 0),
                'calculated_closing' => (int) $healedClosing,
                'variance' => (int) ($healedClosing - ($bill->provisional_closing ?? $bill->calculated_closing ?? 0))
            ];
        }
        
        // No healing possible - return current state
        return [
            'success' => true,
            'status' => $bill->status,
            'action' => 'NO_CHANGE',
            'message' => 'Bill is finalized and no healing data available.',
            'provisional_closing' => (int) ($bill->provisional_closing ?? $bill->calculated_closing ?? 0),
            'calculated_closing' => (int) ($bill->calculated_closing ?? $bill->provisional_closing ?? 0)
        ];
    }

    // ============================================================================
    // SECTION 6: SIMULATION ENGINE
    // Note: This section contains calculation logic that was consolidated from
    // legacy services. CalculatorPHP is now the sole source of billing calculation logic.
    // ============================================================================

    /**
     * Calculate Tiered Cost with Breakdown (Simulation Mode)
     */
    public function calculateTierCost(float $litres, array $tiers): array
    {
        if ($litres <= 0 || empty($tiers)) {
            return ['total_cost' => 0, 'breakdown' => []];
        }

        $remaining = $litres;
        $prev = 0;
        $totalCost = 0;
        $breakdown = [];

        foreach ($tiers as $tier) {
            $max = $tier['max'] ?? ($tier['max'] === null ? PHP_INT_MAX : $tier['max']);
            $rate = $tier['rate'] ?? 0;

            $cap = $max - $prev;
            $used = max(0, min($remaining, $cap));

            if ($used > 0) {
                $cost = ($used / 1000) * $rate;
                $breakdown[] = [
                    'prev' => $prev,
                    'max' => $max === PHP_INT_MAX ? null : $max,
                    'used' => $used,
                    'rate' => $rate,
                    'cost' => $cost
                ];
                $totalCost += $cost;
                $remaining -= $used;
            }

            $prev = $max;
            if ($remaining <= 0) {
                break;
            }
        }

        // Handle remaining usage beyond last tier
        if ($remaining > 0 && !empty($tiers)) {
            $lastTier = end($tiers);
            $lastRate = $lastTier['rate'] ?? 0;
            $cost = ($remaining / 1000) * $lastRate;
            $breakdown[] = [
                'prev' => $prev,
                'max' => null, // Infinity
                'used' => $remaining,
                'rate' => $lastRate,
                'cost' => $cost
            ];
            $totalCost += $cost;
        }

        return [
            'total_cost' => round($totalCost, 2),
            'breakdown' => $breakdown
        ];
    }

    /**
     * Calculate Period to Period billing (Simulation Mode)
     */
    public function calculatePeriodToPeriod(array $readings, RegionsAccountTypeCost $tariff, array $options = []): array
    {
        $readings = $this->normalizeReadings($readings);

        if (count($readings) < 2) {
            return [
                'can_bill' => false,
                'reason' => 'SINGLE_READING_ONLY',
                'periods' => [],
                'total_usage' => 0,
                'total_cost' => 0
            ];
        }

        $billDay = $options['bill_day'] ?? $tariff->billing_day ?? null;
        if (!$billDay) {
            throw new Exception('Bill day is required for Period to Period billing');
        }

        $startDate = $options['start_date'] ?? $readings[0]['date'];

        // Use the BillingPeriodCalculator service for period logic
        $periodCalculator = app(BillingPeriodCalculator::class);
        $periods = $periodCalculator->calculatePeriods(
            $billDay,
            $startDate,
            end($readings)['date']
        );

        // Block-Day Model: Generate Sectors for simulation
        $fakeMeter = (object) ['digit_count' => $options['digit_count'] ?? 4];
        $readingModels = array_map(function ($r) {
            return (object) [
                'reading_date' => $r['date'],
                'reading_value' => $r['value']
            ];
        }, $readings);

        $sectors = $this->generateSectors($readingModels, $fakeMeter);
        $tiers = $this->getTiersFromTariff($tariff);

        $totalUsage = 0;
        $totalCost = 0;
        $processedPeriods = [];

        // Baton Pass: Track sequential readings
        $currentOpeningReading = $readings[0]['value'];

        foreach ($periods as $periodIndex => $period) {
            $periodReadings = $this->attachReadingsToPeriod($period, $readings);

            // Block-Day Model: Use distribute() for simulation accuracy
            $calcResult = $this->distribute($period['start'], $period['end'], $sectors, 0);
            $usage = $calcResult['usage'];
            $momentumRate = $calcResult['rate'];

            $closingReading = $currentOpeningReading + $usage;

            $periodData = [
                'start' => $period['start'],
                'end' => $period['end'],
                'days' => $period['billable_days'] ?? $this->daysBetween($period['start'], $period['end']),
                'usage' => $usage,
                'daily_usage' => $momentumRate,
                'status' => (count($periodReadings) > 0) ? 'ACTUAL' : 'PROVISIONAL',
                'opening_reading' => $currentOpeningReading,
                'closing_reading' => $closingReading,
                'readings' => $periodReadings
            ];

            if ($usage > 0) {
                $tierResult = $this->calculateTierCost($usage, $tiers);
                $periodData['tier_cost'] = $tierResult['total_cost'];
                $periodData['tier_breakdown'] = $tierResult['breakdown'];
            } else {
                $periodData['tier_cost'] = 0;
                $periodData['tier_breakdown'] = [];
            }

            $totalUsage += $usage;
            $totalCost += $periodData['tier_cost'];
            $processedPeriods[] = $periodData;

            // Pass the baton
            $currentOpeningReading = $closingReading;
        }

        return [
            'can_bill' => true,
            'periods' => $processedPeriods,
            'total_usage' => $totalUsage,
            'total_cost' => $totalCost,
            'billing_mode' => 'PERIOD_TO_PERIOD'
        ];
    }

    /**
     * Calculate Date to Date billing (Simulation Mode)
     */
    public function calculateDateToDate(array $readings, RegionsAccountTypeCost $tariff, array $options = []): array
    {
        $readings = $this->normalizeReadings($readings);

        if (count($readings) < 2) {
            return [
                'can_bill' => false,
                'reason' => 'SINGLE_READING_ONLY',
                'sectors' => [],
                'total_usage' => 0,
                'total_cost' => 0
            ];
        }

        // Sort readings by date
        usort($readings, function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        $startDate = $options['start_date'] ?? $readings[0]['date'];
        $tiers = $this->getTiersFromTariff($tariff);
        $sectors = $this->createSectorsFromReadings($readings, $startDate);

        $totalUsage = 0;
        $totalCost = 0;

        foreach ($sectors as &$sector) {
            if ($sector['total_usage'] > 0) {
                $tierResult = $this->calculateTierCost($sector['total_usage'], $tiers);
                $sector['tier_cost'] = $tierResult['total_cost'];
                $sector['tier_breakdown'] = $tierResult['breakdown'];
            } else {
                $sector['tier_cost'] = 0;
                $sector['tier_breakdown'] = [];
            }

            $totalUsage += $sector['total_usage'];
            $totalCost += $sector['tier_cost'];
        }

        return [
            'can_bill' => true,
            'sectors' => $sectors,
            'total_usage' => $totalUsage,
            'total_cost' => $totalCost,
            'billing_mode' => 'DATE_TO_DATE'
        ];
    }

    /**
     * Calculate Bill Breakdown (Simulation Mode)
     */
    public function calculateBill(array $usageData, RegionsAccountTypeCost $tariff): array
    {
        $consumptionCharges = $usageData['total_cost'] ?? 0;

        // Fixed costs
        $fixedCosts = $tariff->fixed_costs ?? [];
        $fixedCostsTotal = array_sum(array_column($fixedCosts, 'value'));

        // Customer costs
        $customerCosts = $tariff->customer_costs ?? [];
        $customerCostsTotal = array_sum(array_column($customerCosts, 'value'));

        // Additional charges
        $additionalCharges = $tariff->additional_charges ?? [];
        $additionalChargesTotal = 0;
        foreach ($additionalCharges as $charge) {
            if (isset($charge['cost'])) {
                $additionalChargesTotal += $charge['cost'];
            } elseif (isset($charge['percentage']) && isset($charge['value'])) {
                $additionalChargesTotal += ($consumptionCharges * $charge['percentage'] / 100);
            }
        }

        // Water out
        $waterOutTotal = 0;
        $waterOutBreakdown = [];
        if (!empty($tariff->water_out) && $usageData['total_usage'] > 0) {
            $waterOutResult = $this->calculateWaterOutCharges($usageData['total_usage'], $tariff->water_out);
            $waterOutTotal = $waterOutResult['total'];
            $waterOutBreakdown = $waterOutResult['items'];
        }

        // Water out related
        $waterOutRelatedTotal = 0;
        if (!empty($tariff->waterout_additional)) {
            foreach ($tariff->waterout_additional as $charge) {
                if (isset($charge['cost'])) {
                    $waterOutRelatedTotal += $charge['cost'];
                }
            }
        }

        // Subtotal
        $subtotal = $consumptionCharges + $fixedCostsTotal + $customerCostsTotal +
            $additionalChargesTotal + $waterOutTotal + $waterOutRelatedTotal;

        // VAT
        $vatRate = $tariff->getVatRate() ?? 0;
        $vatAmount = ($subtotal * $vatRate) / 100;
        $total = $subtotal + $vatAmount;

        return [
            'consumption_charges' => round($consumptionCharges, 2),
            'fixed_costs' => round($fixedCostsTotal, 2),
            'customer_costs' => round($customerCostsTotal, 2),
            'additional_charges' => round($additionalChargesTotal, 2),
            'water_out_charges' => round($waterOutTotal, 2),
            'water_out_related_charges' => round($waterOutRelatedTotal, 2),
            'subtotal' => round($subtotal, 2),
            'vat_rate' => $vatRate,
            'vat_amount' => round($vatAmount, 2),
            'total' => round($total, 2),
            'breakdown' => [
                'consumption' => $usageData['tier_breakdown'] ?? [],
                'fixed_costs' => $fixedCosts,
                'customer_costs' => $customerCosts,
                'additional_charges' => $additionalCharges,
                'water_out' => $waterOutBreakdown,
                'water_out_related' => $tariff->waterout_additional ?? []
            ]
        ];
    }

    // ============================================================================
    // HELPER METHODS FOR SIMULATION MODE
    // ============================================================================

    /**
     * Calculate water out (sewerage) charges
     */
    public function calculateWaterOutCharges(float $usageLitres, array $waterOutTiers): array
    {
        if (empty($waterOutTiers) || $usageLitres <= 0)
            return ['total' => 0, 'items' => []];

        $totalCharge = 0;
        $consumedSoFar = 0;
        $items = [];

        foreach ($waterOutTiers as $tier) {
            $tierMin = floatval($tier['min'] ?? 0);
            $tierMax = isset($tier['max']) ? floatval($tier['max']) : PHP_INT_MAX;
            $costPerKL = floatval($tier['cost'] ?? 0);
            $percentage = floatval($tier['percentage'] ?? 100);

            $tierCapacity = $tierMax - $tierMin;
            $remainingUsage = $usageLitres - $consumedSoFar;

            if ($remainingUsage <= 0)
                break;

            $unitsInTier = min($tierCapacity, $remainingUsage);
            $effectiveUnits = $unitsInTier * ($percentage / 100);
            $unitsForCost = $effectiveUnits / 1000;
            $tierCharge = $unitsForCost * $costPerKL;

            $totalCharge += $tierCharge;
            $items[] = [
                'min' => $tierMin,
                'max' => $tierMax === PHP_INT_MAX ? null : $tierMax,
                'units_in_tier' => $unitsInTier,
                'effective_units' => $effectiveUnits,
                'units_for_cost' => $unitsForCost,
                'cost_per_unit' => $costPerKL,
                'percentage' => $percentage,
                'charge' => round($tierCharge, 2)
            ];
            $consumedSoFar += $unitsInTier;
        }

        return ['total' => round($totalCharge, 2), 'items' => $items];
    }

    /**
     * Block-Day Model: Inclusive days calculation (public helper)
     */
    public function daysBetween($dateA, $dateB): int
    {
        return $this->getBlockDays($dateA, $dateB);
    }

    /**
     * Extract tiers from tariff model
     */
    protected function getTiersFromTariff(RegionsAccountTypeCost $tariff): array
    {
        $tiers = $tariff->water_in ?? [];
        if (!empty($tiers)) {
            $lastIndex = count($tiers) - 1;
            if (!isset($tiers[$lastIndex]['max']) || $tiers[$lastIndex]['max'] === null) {
                $tiers[$lastIndex]['max'] = PHP_INT_MAX;
            }
        }
        return $tiers;
    }

    /**
     * Normalize readings array
     */
    protected function normalizeReadings(array $readings): array
    {
        $normalized = [];
        foreach ($readings as $reading) {
            if (!isset($reading['date']) || !isset($reading['value']))
                continue;
            // Block-Day Model: Truncate to integers
            $normalized[] = [
                'date' => $reading['date'],
                'value' => floor(floatval($reading['value'])),
                'type' => $reading['type'] ?? 'ACTUAL'
            ];
        }
        usort($normalized, function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });
        return $normalized;
    }

    /**
     * Attach readings to a period
     */
    protected function attachReadingsToPeriod(array $period, array $readings): array
    {
        $periodStart = Carbon::parse($period['start']);
        $periodEnd = Carbon::parse($period['end']);
        $periodReadings = [];
        foreach ($readings as $reading) {
            $readingDate = Carbon::parse($reading['date']);
            if ($readingDate->gte($periodStart) && $readingDate->lt($periodEnd)) {
                $periodReadings[] = $reading;
            }
        }
        return $periodReadings;
    }

    /**
     * Create sectors from readings array
     */
    protected function createSectorsFromReadings(array $readings, string $startDate): array
    {
        if (count($readings) < 2)
            return [];

        $sectors = [];
        $sectorId = 1;
        $previousSectorEndReading = null;

        for ($i = 0; $i < count($readings) - 1; $i++) {
            $earlier = $readings[$i];
            $later = $readings[$i + 1];

            // Block-Day Model: Baton Pass - closing reading = next opening reading
            $sectorStartReading = ($i === 0) ? $earlier['value'] : ($previousSectorEndReading ?? $earlier['value']);
            $sectorEndReading = $later['value'];

            $sectorStartDate = $i === 0 ? $this->asDate($earlier['date']) : $this->asDate($earlier['date']);
            $sectorEndDate = $this->asDate($later['date']);

            // Block-Day Model: Delta Rule - BlockCount
            $measuredDays = max(1, $this->getBlockDays($sectorStartDate, $sectorEndDate));
            $sectorUsage = $sectorEndReading - $sectorStartReading;

            $sector = [
                'sector_id' => $sectorId++,
                'start_date' => $sectorStartDate->format('Y-m-d'),
                'end_date' => $sectorEndDate->format('Y-m-d'),
                'start_reading' => $sectorStartReading,
                'end_reading' => $sectorEndReading,
                'total_usage' => $sectorUsage,
                'days' => $measuredDays,
                'daily_usage' => $sectorUsage / $measuredDays,
                'status' => 'CLOSED',
                'readings' => [
                    ['date' => $earlier['date'], 'value' => $earlier['value']],
                    ['date' => $later['date'], 'value' => $later['value']]
                ]
            ];
            $sectors[] = $sector;
            // Block-Day Model: Baton Pass
            $previousSectorEndReading = $sectorEndReading;
        }
        return $sectors;
    }
}