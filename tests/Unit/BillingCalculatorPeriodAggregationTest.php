<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingCalculatorPeriodToPeriod;
use Carbon\Carbon;

/**
 * UNIT 2.5: Period Usage Aggregation - Parity Tests
 * 
 * Tests PHP calculator period aggregation against JavaScript behavior
 */
class BillingCalculatorPeriodAggregationTest extends TestCase
{
    private $calculator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCalculatorPeriodToPeriod();
    }
    
    /**
     * Test 1: Period with multiple sectors (within same period)
     * 
     * JavaScript behavior:
     * - Period 1: 2026-01-15 -> 2026-02-15
     * - Sector 1: 1000 L usage, 10 days
     * - Sector 2: 2000 L usage, 15 days
     * - Expected: period.usage = 1000 + 2000 = 3000 L
     * - Expected: period.dailyUsage = 3000 / period_days (where period_days = days_between(start, end-1))
     */
    public function testPeriodWithMultipleSectors(): void
    {
        // Create Period 1: Jan 15 -> Feb 15
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add multiple readings within period
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        $this->calculator->addReading(0, '2026-01-30', 3000.0);
        
        // Recalculate period from sectors
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        
        // Verify usage is aggregated from sectors
        $this->assertNotNull($period['usage'], 'Period usage should be calculated');
        $this->assertGreaterThan(0, $period['usage'], 'Period usage should be > 0');
        
        // Verify daily usage is calculated (total_usage / period_days)
        $this->assertNotNull($period['dailyUsage'], 'Period daily usage should be calculated');
        $this->assertGreaterThan(0, $period['dailyUsage'], 'Period daily usage should be > 0');
        
        // Verify status is set to CALCULATED
        $this->assertEquals('CALCULATED', $period['status'], 'Period status should be CALCULATED');
    }
    
    /**
     * Test 2: Period with single sector (no splitting)
     * 
     * JavaScript behavior:
     * - Period 1: 2026-01-15 -> 2026-02-15
     * - Sector: 1000 L usage
     * - Expected: period.usage = 1000 L
     */
    public function testPeriodWithSingleSector(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add single reading within period
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        
        // Should have 1 sector with 1000 L usage
        $sectors = $period['sectors'] ?? [];
        $this->assertCount(1, $sectors, 'Period should have 1 sector');
        
        $expectedUsage = 1000.0; // 2000 - 1000
        $this->assertEquals($expectedUsage, $period['usage'], '', 0.01, 'Period usage should equal sector usage');
    }
    
    /**
     * Test 3: Period following a split sector (multiple sub-sectors)
     * 
     * JavaScript behavior:
     * - Period 1: 2026-01-15 -> 2026-02-15
     * - Period 2: 2026-02-15 -> 2026-03-15
     * - Sector crossing boundary: Feb 10 -> Feb 20
     *   - Sub-sector 1a: Feb 10 -> Feb 15 (Period 1), usage = X
     *   - Sub-sector 1b: Feb 15 -> Feb 20 (Period 2), usage = Y
     * - Expected: Period 2 usage = Y (from sub-sector 1b)
     */
    public function testPeriodWithSplitSector(): void
    {
        // Create 2 periods
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add reading spanning period boundary
        $this->calculator->addReading(0, '2026-02-10', 1000.0);
        $this->calculator->addReading(1, '2026-02-20', 2000.0);
        
        // Recalculate both periods
        $this->calculator->recalculatePeriodFromSectors(0);
        $this->calculator->recalculatePeriodFromSectors(1);
        
        $periods = $this->calculator->getPeriods();
        
        // Period 1 should have sub-sector 1a
        $period1 = $periods[0];
        $sectors1 = $period1['sectors'] ?? [];
        $this->assertNotEmpty($sectors1, 'Period 1 should have sectors');
        
        // Period 2 should have sub-sector 1b
        $period2 = $periods[1];
        $sectors2 = $period2['sectors'] ?? [];
        $this->assertNotEmpty($sectors2, 'Period 2 should have sectors');
        
        // Verify usage is aggregated from sub-sectors
        $this->assertNotNull($period2['usage'], 'Period 2 usage should be calculated');
        $this->assertGreaterThan(0, $period2['usage'], 'Period 2 usage should be > 0');
    }
    
    /**
     * Test 4: Period with zero sectors (empty period)
     * 
     * JavaScript behavior:
     * - If period_sectors.length === 0, return early (no calculation)
     * - Period usage remains null
     */
    public function testPeriodWithZeroSectors(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // No readings added
        
        // Recalculate period from sectors
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        
        // Period should not have usage calculated (early return)
        // Note: This depends on JS behavior - if no sectors, usage remains null
        $sectors = $period['sectors'] ?? [];
        $this->assertEmpty($sectors, 'Period should have no sectors');
    }
    
    /**
     * Test 5: Period at start of bill lifecycle (Period 0)
     * 
     * JavaScript behavior:
     * - Period 0: closing = (start_reading || 0) + total_usage
     * - start_reading is set from first reading in period
     */
    public function testPeriodAtStart(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        
        // Set start_reading (as JS calculate() does)
        $periods = $this->calculator->getPeriods();
        $readings = $periods[0]['readings'] ?? [];
        $validReadings = array_filter($readings, function($r) {
            return isset($r['date']) && $r['date'] !== null && isset($r['value']) && $r['value'] !== null;
        });
        if (!empty($validReadings)) {
            $sortedReadings = array_values($validReadings);
            usort($sortedReadings, function($a, $b) {
                return Carbon::parse($a['date'])->timestamp <=> Carbon::parse($b['date'])->timestamp;
            });
            $periods[0]['start_reading'] = $sortedReadings[0]['value'];
            $this->calculator->setPeriods($periods);
        }
        
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        
        // Verify closing reading calculation
        if (isset($period['start_reading']) && $period['start_reading'] !== null) {
            $expectedClosing = $period['start_reading'] + $period['usage'];
            $this->assertEquals($expectedClosing, $period['closing'], '', 0.01, 'Period 0 closing should be start_reading + usage');
        }
    }
    
    /**
     * Test 6: Period following another period (Period 1+)
     * 
     * JavaScript behavior:
     * - Period N: opening = prev_period.closing
     * - Period N: closing = (opening || 0) + total_usage
     */
    public function testPeriodFollowingAnotherPeriod(): void
    {
        // Create 2 periods
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        
        // Period 2
        $this->calculator->addReading(1, '2026-02-20', 3000.0);
        $this->calculator->addReading(1, '2026-02-25', 4000.0);
        
        // Set start_reading for Period 1
        $periods = $this->calculator->getPeriods();
        $readings = $periods[0]['readings'] ?? [];
        $validReadings = array_filter($readings, function($r) {
            return isset($r['date']) && $r['date'] !== null && isset($r['value']) && $r['value'] !== null;
        });
        if (!empty($validReadings)) {
            $sortedReadings = array_values($validReadings);
            usort($sortedReadings, function($a, $b) {
                return Carbon::parse($a['date'])->timestamp <=> Carbon::parse($b['date'])->timestamp;
            });
            $periods[0]['start_reading'] = $sortedReadings[0]['value'];
            $this->calculator->setPeriods($periods);
        }
        
        // Recalculate Period 1 first
        $this->calculator->recalculatePeriodFromSectors(0);
        
        // Recalculate Period 2
        $this->calculator->recalculatePeriodFromSectors(1);
        
        $periods = $this->calculator->getPeriods();
        $period1 = $periods[0];
        $period2 = $periods[1];
        
        // Verify Period 2 opening = Period 1 closing
        $this->assertEquals($period1['closing'], $period2['opening'], 'Period 2 opening should equal Period 1 closing');
        
        // Verify Period 2 closing = opening + usage
        $expectedClosing2 = ($period2['opening'] ?? 0) + $period2['usage'];
        $this->assertEquals($expectedClosing2, $period2['closing'], '', 0.01, 'Period 2 closing should be opening + usage');
    }
    
    /**
     * Test 7: Period daily usage calculation (total_usage / period_days)
     * 
     * JavaScript behavior:
     * - period_days = days_between(p.start, end_display) where end_display = p.end - 1
     * - dailyUsage = total_usage / period_days (uses period_days, NOT total_days from sectors)
     */
    public function testPeriodDailyUsageCalculation(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period: Jan 15 -> Feb 15 (31 days, but period_days uses end_display = Feb 14)
        // period_days = days_between(Jan 15, Feb 14) = 31 days (inclusive-inclusive)
        
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        
        // Calculate expected period_days
        $periodStart = Carbon::parse($period['start']);
        $periodEnd = Carbon::parse($period['end']);
        $endDisplay = Carbon::parse($periodEnd)->copy()->subDay();
        $expectedPeriodDays = $this->calculator->daysBetween($periodStart, $endDisplay);
        
        // Verify daily usage calculation
        if ($expectedPeriodDays > 0 && $period['usage'] !== null) {
            $expectedDailyUsage = $period['usage'] / $expectedPeriodDays;
            $this->assertEquals($expectedDailyUsage, $period['dailyUsage'], '', 0.01, 'Period daily usage should be usage / period_days');
        }
    }
    
    /**
     * Test 8: Usage aggregation from multiple sectors
     * 
     * JavaScript behavior:
     * - total_usage = sum(s.usage_in_period ?? s.total_usage ?? s.sector_usage ?? 0)
     * - Must preserve exact sum
     */
    public function testUsageAggregationFromMultipleSectors(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add multiple readings creating multiple sectors
        $this->calculator->addReading(0, '2026-01-15', 1000.0);
        $this->calculator->addReading(0, '2026-01-20', 2000.0);
        $this->calculator->addReading(0, '2026-01-25', 3000.0);
        $this->calculator->addReading(0, '2026-01-30', 4000.0);
        
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        $sectors = $period['sectors'] ?? [];
        
        // Calculate expected total usage from sectors
        $expectedTotalUsage = 0;
        foreach ($sectors as $s) {
            $usage = $s['usage_in_period'] ?? $s['total_usage'] ?? $s['sector_usage'] ?? 0;
            $expectedTotalUsage += $usage;
        }
        
        // Verify period usage equals sum of sector usage
        $this->assertEquals($expectedTotalUsage, $period['usage'], '', 0.01, 'Period usage should equal sum of sector usage');
    }
    
    /**
     * Test 9: Negative usage prevention
     * 
     * JavaScript behavior:
     * - If total_usage < 0, throw error
     * - Error: "Period usage cannot be negative"
     */
    public function testNegativeUsagePrevention(): void
    {
        // This test is more of a validation test - actual prevention happens at sector level
        // If sectors are valid, period usage should be >= 0
        // If invalid sectors exist, they should be caught during sector creation/validation
        
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add valid readings
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        
        // Should not throw (valid readings)
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $period = $periods[0];
        
        $this->assertGreaterThanOrEqual(0, $period['usage'], 'Period usage should be >= 0');
    }
    
    /**
     * Test 10: Period status set to CALCULATED
     * 
     * JavaScript behavior:
     * - After recalculate_period_from_sectors, status = "CALCULATED"
     */
    public function testPeriodStatusSetToCalculated(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-20', 1000.0);
        $this->calculator->addReading(0, '2026-01-25', 2000.0);
        
        // Period starts as PROVISIONAL
        $periods = $this->calculator->getPeriods();
        $this->assertEquals('PROVISIONAL', $periods[0]['status'], 'Period should start as PROVISIONAL');
        
        // After recalculation, status should be CALCULATED
        $this->calculator->recalculatePeriodFromSectors(0);
        
        $periods = $this->calculator->getPeriods();
        $this->assertEquals('CALCULATED', $periods[0]['status'], 'Period status should be CALCULATED after recalculation');
    }
}










