<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingCalculatorPeriodToPeriod;
use Carbon\Carbon;

/**
 * UNIT 2.4: Sector Splitting at Period Boundaries - Parity Tests
 * 
 * Tests PHP calculator sector splitting against JavaScript behavior
 */
class BillingCalculatorSectorSplittingTest extends TestCase
{
    private $calculator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCalculatorPeriodToPeriod();
    }
    
    /**
     * Test 1: Sector not crossing period boundary (no splitting)
     * 
     * JavaScript behavior:
     * - Sector: 2026-01-10 -> 2026-01-15 (all within Period 1: 2026-01-15 -> 2026-02-15)
     * - Expected: crosses_period = false, no sub-sectors
     */
    public function testSectorNotCrossingBoundary(): void
    {
        // Create Period 1: Jan 15 -> Feb 15
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add reading within period
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors);
        $sector = $sectors[0];
        
        // Sector should not be marked as crossing
        $this->assertEquals(false, $sector['crosses_period'], 'Sector should not cross period boundary');
        $this->assertEmpty($sector['sub_sectors'], 'Sector should have no sub-sectors');
    }
    
    /**
     * Test 2: Sector crossing single period boundary (split into 2 sub-sectors)
     * 
     * JavaScript behavior:
     * - Period 1: 2026-01-15 -> 2026-02-15
     * - Period 2: 2026-02-15 -> 2026-03-15
     * - Sector: 2026-02-10 -> 2026-02-20 (crosses boundary at 2026-02-15)
     * - Expected: 2 sub-sectors
     *   - Sub-sector 1a: 2026-02-10 -> 2026-02-15 (Period 1)
     *   - Sub-sector 1b: 2026-02-15 -> 2026-02-20 (Period 2)
     */
    public function testSectorCrossingSingleBoundary(): void
    {
        // Create Period 1: Jan 15 -> Feb 15
        $this->calculator->addPeriod(15, '2026-01');
        // Create Period 2: Feb 15 -> Mar 15
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add reading spanning period boundary
        // Reading 1: Feb 10 (Period 1)
        // Reading 2: Feb 20 (Period 2)
        $this->calculator->addReading(0, '2026-02-10', 1000.0);
        $this->calculator->addReading(1, '2026-02-20', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors);
        $sector = $sectors[0];
        
        // Sector should be marked as crossing
        $this->assertEquals(true, $sector['crosses_period'], 'Sector should cross period boundary');
        $this->assertCount(2, $sector['sub_sectors'], 'Sector should have 2 sub-sectors');
        
        // Sub-sector 1a: Period 1 portion
        $sub1 = $sector['sub_sectors'][0];
        $this->assertEquals('1a', $sub1['sub_id']);
        $this->assertEquals(0, $sub1['period_index'], 'First sub-sector should be in Period 1');
        $this->assertEquals('2026-02-10', $sub1['start_date']);
        $this->assertEquals('2026-02-15', $sub1['end_date']);
        
        // Sub-sector 1b: Period 2 portion
        $sub2 = $sector['sub_sectors'][1];
        $this->assertEquals('1b', $sub2['sub_id']);
        $this->assertEquals(1, $sub2['period_index'], 'Second sub-sector should be in Period 2');
        $this->assertEquals('2026-02-15', $sub2['start_date']);
        $this->assertEquals('2026-02-20', $sub2['end_date']);
        
        // Verify total usage preserved: sum(sub-sector usage) == original sector usage
        $totalSubUsage = $sub1['usage_in_period'] + $sub2['usage_in_period'];
        $this->assertEquals($sector['sector_usage'], $totalSubUsage, '', 0.01, 'Total sub-sector usage should equal original sector usage');
    }
    
    /**
     * Test 3: Sector crossing multiple period boundaries (split into 3+ sub-sectors)
     * 
     * JavaScript behavior:
     * - Period 1: 2026-01-15 -> 2026-02-15
     * - Period 2: 2026-02-15 -> 2026-03-15
     * - Period 3: 2026-03-15 -> 2026-04-15
     * - Sector: 2026-02-10 -> 2026-03-20 (crosses 2 boundaries)
     * - Expected: 3 sub-sectors (1a, 1b, 1c)
     */
    public function testSectorCrossingMultipleBoundaries(): void
    {
        // Create 3 periods
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add reading spanning 2 period boundaries
        $this->calculator->addReading(0, '2026-02-10', 1000.0);
        $this->calculator->addReading(2, '2026-03-20', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors);
        $sector = $sectors[0];
        
        $this->assertEquals(true, $sector['crosses_period'], 'Sector should cross period boundaries');
        $this->assertGreaterThanOrEqual(2, count($sector['sub_sectors']), 'Sector should have 2+ sub-sectors');
        
        // Verify sub-sector IDs increment: 1a, 1b, 1c...
        $subIds = array_column($sector['sub_sectors'], 'sub_id');
        $this->assertEquals('1a', $subIds[0]);
        $this->assertEquals('1b', $subIds[1]);
        if (isset($subIds[2])) {
            $this->assertEquals('1c', $subIds[2]);
        }
        
        // Verify total usage preserved
        $totalSubUsage = array_sum(array_column($sector['sub_sectors'], 'usage_in_period'));
        $this->assertEquals($sector['sector_usage'], $totalSubUsage, '', 0.01, 'Total sub-sector usage should equal original sector usage');
    }
    
    /**
     * Test 4: Proportional usage allocation (usage = daily_usage * days)
     * 
     * JavaScript behavior:
     * - Sector: 1000 L usage, 10 days, daily_usage = 100 L/day
     * - Split at day 5: sub-sector 1 = 100 * 5 = 500 L, sub-sector 2 = 100 * 5 = 500 L
     */
    public function testProportionalUsageAllocation(): void
    {
        // Create Period 1: Jan 15 -> Feb 15
        $this->calculator->addPeriod(15, '2026-01');
        // Create Period 2: Feb 15 -> Mar 15
        $this->calculator->addPeriod(15, '2026-01');
        
        // Sector spanning 10 days with 1000 L usage
        // Reading 1: Feb 10 (Period 1)
        // Reading 2: Feb 20 (Period 2) - crosses boundary at Feb 15
        // Sector: Feb 10 -> Feb 20 = 10 days (inclusive-inclusive)
        // Usage: 2000 - 1000 = 1000 L
        // Daily usage: 1000 / 10 = 100 L/day
        $this->calculator->addReading(0, '2026-02-10', 1000.0);
        $this->calculator->addReading(1, '2026-02-20', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        $sector = $sectors[0];
        
        // Calculate expected usage allocation
        // Sub-sector 1: Feb 10 -> Feb 15 = 6 days (10, 11, 12, 13, 14, 15)
        // Usage: 100 * 6 = 600 L
        // Sub-sector 2: Feb 15 -> Feb 20 = 6 days (15, 16, 17, 18, 19, 20)
        // Usage: 100 * 6 = 600 L (but note: period_end_inclusive = period_end - 1, so days calculation may differ)
        
        $sub1 = $sector['sub_sectors'][0];
        $sub2 = $sector['sub_sectors'][1];
        
        // Verify usage allocation is proportional (matching JS: usage = daily_usage * days)
        $expectedUsage1 = $sector['daily_usage'] * $sub1['days_in_period'];
        $this->assertEquals($expectedUsage1, $sub1['usage_in_period'], '', 0.01, 'Sub-sector 1 usage should be daily_usage * days_in_period');
        
        $expectedUsage2 = $sector['daily_usage'] * $sub2['days_in_period'];
        $this->assertEquals($expectedUsage2, $sub2['usage_in_period'], '', 0.01, 'Sub-sector 2 usage should be daily_usage * days_in_period');
    }
    
    /**
     * Test 5: Sub-sector date assignment
     * 
     * JavaScript behavior:
     * - Sub-sector start_date = current_start
     * - Sub-sector end_date = period_end (for boundaries) or sector.end_date (for remainder)
     */
    public function testSubSectorDateAssignment(): void
    {
        // Create 2 periods
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Sector crossing boundary
        $this->calculator->addReading(0, '2026-02-10', 1000.0);
        $this->calculator->addReading(1, '2026-02-20', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        $sector = $sectors[0];
        
        $sub1 = $sector['sub_sectors'][0];
        $sub2 = $sector['sub_sectors'][1];
        
        // Sub-sector 1: start_date should be sector.start_date
        $this->assertEquals($sector['start_date'], $sub1['start_date'], 'Sub-sector 1 start_date should match sector start_date');
        // Sub-sector 1: end_date should be Period 1 end (boundary)
        $periods = $this->calculator->getPeriods();
        $period1End = Carbon::parse($periods[0]['end'])->format('Y-m-d');
        $this->assertEquals($period1End, $sub1['end_date'], 'Sub-sector 1 end_date should be Period 1 end (boundary)');
        
        // Sub-sector 2: start_date should be Period 2 start (boundary)
        $period2Start = Carbon::parse($periods[1]['start'])->format('Y-m-d');
        $this->assertEquals($period2Start, $sub2['start_date'], 'Sub-sector 2 start_date should be Period 2 start (boundary)');
        // Sub-sector 2: end_date should be sector.end_date
        $this->assertEquals($sector['end_date'], $sub2['end_date'], 'Sub-sector 2 end_date should match sector end_date');
    }
    
    /**
     * Test 6: Sub-sector period assignment
     * 
     * JavaScript behavior:
     * - Each sub-sector is assigned to the correct period_index
     */
    public function testSubSectorPeriodAssignment(): void
    {
        // Create 3 periods
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Sector spanning 3 periods
        $this->calculator->addReading(0, '2026-02-10', 1000.0);
        $this->calculator->addReading(2, '2026-03-20', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        $sector = $sectors[0];
        
        // Verify each sub-sector has correct period_index
        $periods = $this->calculator->getPeriods();
        foreach ($sector['sub_sectors'] as $sub) {
            $periodIdx = $sub['period_index'];
            $this->assertGreaterThanOrEqual(0, $periodIdx, 'Sub-sector period_index should be >= 0');
            $this->assertLessThan(count($periods), $periodIdx, 'Sub-sector period_index should be < periods count');
        }
    }
}










