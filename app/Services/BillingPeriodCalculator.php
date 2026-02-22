<?php

namespace App\Services;

use Carbon\Carbon;
use DateTime;

/**
 * BillingPeriodCalculator
 * 
 * ARCHITECTURE REFERENCE: See docs/ComprehensiveBillingArchitecture.md
 * 
 * ROLE: Policy layer - calculates billing periods based on bill day
 * 
 * RESPONSIBILITIES:
 * - Identifies all periods between two readings
 * - Calculates billable days (intersection of period and reading span)
 * - Returns precomputed periods for BillingEngine
 * - Handles cross-month spans correctly
 * 
 * INPUTS: Bill day, reading date range, existing bills
 * OUTPUTS: Array of periods with {start, end, billable_days}
 * 
 * INTERACTS WITH:
 * - Called by: MeterReadingObserver, BillingController wrappers
 * - Calls: None (pure policy logic)
 * - Consumed by: BillingEngine.process()
 * 
 * DATE RULE (LOCKED):
 * - Start date: Inclusive
 * - End date: Exclusive (first day of next period)
 * - Formula: days = (end_date - start_date)
 * 
 * @see docs/ComprehensiveBillingArchitecture.md for complete architecture
 */
class BillingPeriodCalculator
{
    /**
     * Calculate all billing periods between two reading dates
     * 
     * @param int $billDay Bill day (1-31)
     * @param string $startDate First reading date (Y-m-d)
     * @param string $endDate Last reading date (Y-m-d)
     * @param array $existingBills Existing bills to avoid duplicates (optional)
     * @return array Array of periods with start, end, and billable_days
     */
    public function calculatePeriods(
        int $billDay,
        string $startDate,
        string $endDate,
        array $existingBills = []
    ): array {
        // Validate bill day
        if ($billDay < 1 || $billDay > 31) {
            throw new \InvalidArgumentException("Bill day must be between 1 and 31, got: {$billDay}");
        }

        // Parse dates
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($start->gt($end)) {
            throw new \InvalidArgumentException("Start date must be before or equal to end date");
        }

        // Find the period that contains the start date
        $firstPeriodStart = $this->findPeriodStartForDate($start, $billDay);
        
        // Generate all periods from first period start to past end date
        $periods = [];
        $currentPeriodStart = Carbon::parse($firstPeriodStart);
        
        // Continue until we've generated a period that starts after the end date
        // We need to check one period past the end date to catch periods that contain the end date
        $endPlusOnePeriod = $end->copy()->addMonth();
        while ($currentPeriodStart->lte($endPlusOnePeriod)) {
            // Calculate period end (exclusive - first day of next period)
            $periodEndStr = $this->calculatePeriodEnd($currentPeriodStart, $billDay);
            $periodEnd = Carbon::parse($periodEndStr);
            
            // Only include periods that intersect with the reading span
            if ($this->periodsIntersect($currentPeriodStart, $periodEnd, $start, $end)) {
                // Calculate billable days (intersection of period and reading span)
                $billableDays = $this->calculateBillableDays(
                    $currentPeriodStart->format('Y-m-d'),
                    $periodEnd->format('Y-m-d'),
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d')
                );
                
                // Only add period if it has billable days
                if ($billableDays > 0) {
                    $periods[] = [
                        'start' => $currentPeriodStart->format('Y-m-d'),  // Inclusive
                        'end' => $periodEnd->format('Y-m-d'),            // Exclusive
                        'billable_days' => $billableDays
                    ];
                }
            }
            
            // Move to next period
            $currentPeriodStart = $this->calculateNextPeriodStart($currentPeriodStart, $billDay);
        }

        return $periods;
    }

    /**
     * Find the start date of the period that contains the given date
     * 
     * Periods always start on the bill day.
     * If the date is before the bill day in its month, the period starts on the previous month's bill day.
     * If the date is on or after the bill day, the period starts on the current month's bill day.
     * 
     * @param Carbon|string $date The date to find the period for (Carbon instance or Y-m-d string)
     * @param int $billDay The bill day (1-31)
     * @return string Period start date (Y-m-d)
     */
    public function findPeriodStartForDate($date, int $billDay): string
    {
        // Handle both Carbon and string inputs
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        $dayOfMonth = $date->day;
        
        if ($dayOfMonth < $billDay) {
            // Date is before bill day - period started on previous month's bill day
            return $date->copy()->subMonth()->day($billDay)->format('Y-m-d');
        } else {
            // Date is on or after bill day - period started on current month's bill day
            return $date->copy()->day($billDay)->format('Y-m-d');
        }
    }

    /**
     * Calculate the end date of a period (exclusive - first day of next period)
     * 
     * @param Carbon|string $periodStart Period start date (Carbon instance or Y-m-d string)
     * @param int $billDay Bill day (1-31)
     * @return string Period end date (Y-m-d, exclusive)
     */
    public function calculatePeriodEnd($periodStart, int $billDay): string
    {
        // Handle both Carbon and string inputs
        if (is_string($periodStart)) {
            $periodStart = Carbon::parse($periodStart);
        }
        
        // Period ends on the bill day of the next month (exclusive)
        return $periodStart->copy()->addMonth()->day($billDay)->format('Y-m-d');
    }

    /**
     * Calculate the start date of the next period
     * 
     * @param Carbon $currentPeriodStart Current period start
     * @param int $billDay Bill day (1-31)
     * @return Carbon Next period start
     */
    private function calculateNextPeriodStart(Carbon $currentPeriodStart, int $billDay): Carbon
    {
        return $currentPeriodStart->copy()->addMonth()->day($billDay);
    }

    /**
     * Check if a period intersects with a date range
     * 
     * @param Carbon $periodStart Period start (inclusive)
     * @param Carbon $periodEnd Period end (exclusive)
     * @param Carbon $rangeStart Range start (inclusive)
     * @param Carbon $rangeEnd Range end (inclusive)
     * @return bool True if periods intersect
     */
    private function periodsIntersect(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): bool {
        // Period: [periodStart, periodEnd) - end is exclusive
        // Range: [rangeStart, rangeEnd] - both inclusive
        
        // Intersection exists if:
        // - periodStart <= rangeEnd (inclusive) AND periodEnd > rangeStart
        // Convert rangeEnd to exclusive for comparison: rangeEnd + 1 day
        $rangeEndExclusive = $rangeEnd->copy()->addDay();
        return $periodStart->lt($rangeEndExclusive) && $periodEnd->gt($rangeStart);
    }

    /**
     * Calculate billable days (intersection of period and reading span)
     * 
     * DATE RULE: 
     * - Period: Start inclusive, end exclusive
     * - Reading span: Start inclusive, end inclusive
     * - Intersection: Start inclusive, end exclusive
     * 
     * @param string $periodStart Period start (Y-m-d, inclusive)
     * @param string $periodEnd Period end (Y-m-d, exclusive)
     * @param string $readingStart Reading span start (Y-m-d, inclusive)
     * @param string $readingEnd Reading span end (Y-m-d, inclusive)
     * @return int Number of billable days
     */
    public function calculateBillableDays(
        string $periodStart,
        string $periodEnd,
        string $readingStart,
        string $readingEnd
    ): int {
        // Parse dates
        $periodStartDate = Carbon::parse($periodStart);
        $periodEndDate = Carbon::parse($periodEnd);  // Exclusive
        $readingStartDate = Carbon::parse($readingStart);
        $readingEndDate = Carbon::parse($readingEnd);

        // Find intersection
        // Intersection start: max(periodStart, readingStart) - both inclusive
        $intersectionStart = $periodStartDate->gt($readingStartDate) ? $periodStartDate : $readingStartDate;
        
        // Intersection end: min(periodEnd, readingEnd + 1 day)
        // - periodEnd is exclusive
        // - readingEnd is inclusive, so add 1 day to make it exclusive for comparison
        $readingEndExclusive = $readingEndDate->copy()->addDay();
        $intersectionEnd = $periodEndDate->lt($readingEndExclusive) ? $periodEndDate : $readingEndExclusive;

        // Calculate days (end is exclusive, so no +1)
        if ($intersectionStart->gte($intersectionEnd)) {
            return 0;
        }

        return $intersectionStart->diffInDays($intersectionEnd);
    }

    /**
     * Find which period a date belongs to
     * 
     * @param string $date Date to check (Y-m-d)
     * @param int $billDay Bill day (1-31)
     * @return array Period with start and end dates
     */
    public function findPeriodForDate(string $date, int $billDay): array
    {
        $dateCarbon = Carbon::parse($date);
        $periodStart = $this->findPeriodStartForDate($dateCarbon, $billDay);
        $periodStartCarbon = Carbon::parse($periodStart);
        $periodEnd = $this->calculatePeriodEnd($periodStartCarbon, $billDay);

        return [
            'start' => $periodStart,
            'end' => $periodEnd->format('Y-m-d'),
            'billable_days' => 0  // Not calculated here
        ];
    }
}

