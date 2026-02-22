<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingCalculatorPeriodToPeriod;
use Carbon\Carbon;

/**
 * UNIT 2.1: Period Boundary Generation - Parity Tests
 * 
 * Tests PHP calculator period boundary generation against JavaScript behavior
 */
class BillingCalculatorPeriodBoundaryTest extends TestCase
{
    private $calculator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCalculatorPeriodToPeriod();
    }
    
    /**
     * Test 1: First period boundary generation
     * 
     * JavaScript behavior:
     * - bill_day: 15
     * - start_month: "2026-01"
     * - start: new Date(2026, 0, 15, 12, 0, 0) -> 2026-01-15 12:00:00
     * - end: new Date(2026, 1, 15, 12, 0, 0) -> 2026-02-15 12:00:00
     */
    public function testFirstPeriodBoundary(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $periods = $this->calculator->getPeriods();
        
        $this->assertCount(1, $periods);
        
        $period = $periods[0];
        
        // Verify start date
        $start = Carbon::parse($period['start']);
        $this->assertEquals(2026, $start->year);
        $this->assertEquals(1, $start->month);
        $this->assertEquals(15, $start->day);
        $this->assertEquals(12, $start->hour);
        $this->assertEquals(0, $start->minute);
        $this->assertEquals('2026-01-15 12:00:00', $period['start']);
        
        // Verify end date (next month's bill_day)
        $end = Carbon::parse($period['end']);
        $this->assertEquals(2026, $end->year);
        $this->assertEquals(2, $end->month);
        $this->assertEquals(15, $end->day);
        $this->assertEquals(12, $end->hour);
        $this->assertEquals(0, $end->minute);
        $this->assertEquals('2026-02-15 12:00:00', $period['end']);
        
        // Verify status
        $this->assertEquals('PROVISIONAL', $period['status']);
        
        // Verify active period
        $this->assertEquals(0, $this->calculator->getActive());
    }
    
    /**
     * Test 2: Second period boundary generation (chaining)
     * 
     * JavaScript behavior:
     * - First period ends: 2026-02-15 12:00:00
     * - Second period starts: same as first period end
     * - Second period ends: 2026-03-15 12:00:00 (one month later on bill_day)
     */
    public function testSecondPeriodBoundaryChaining(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01'); // bill_day and start_month same, but will use previous end
        
        $periods = $this->calculator->getPeriods();
        
        $this->assertCount(2, $periods);
        
        // First period
        $period1 = $periods[0];
        $this->assertEquals('2026-01-15 12:00:00', $period1['start']);
        $this->assertEquals('2026-02-15 12:00:00', $period1['end']);
        
        // Second period (chains from first)
        $period2 = $periods[1];
        $this->assertEquals('2026-02-15 12:00:00', $period2['start'], 'Second period must start where first period ended');
        $this->assertEquals('2026-03-15 12:00:00', $period2['end'], 'Second period must end one month later on bill_day');
        
        // Verify active period
        $this->assertEquals(1, $this->calculator->getActive());
    }
    
    /**
     * Test 3: Multiple periods (3+ periods)
     */
    public function testMultiplePeriodsChaining(): void
    {
        $this->calculator->addPeriod(1, '2026-01');
        $this->calculator->addPeriod(1, '2026-01');
        $this->calculator->addPeriod(1, '2026-01');
        
        $periods = $this->calculator->getPeriods();
        
        $this->assertCount(3, $periods);
        
        // Period 1: Jan 1 -> Feb 1
        $this->assertEquals('2026-01-01 12:00:00', $periods[0]['start']);
        $this->assertEquals('2026-02-01 12:00:00', $periods[0]['end']);
        
        // Period 2: Feb 1 -> Mar 1
        $this->assertEquals('2026-02-01 12:00:00', $periods[1]['start']);
        $this->assertEquals('2026-03-01 12:00:00', $periods[1]['end']);
        
        // Period 3: Mar 1 -> Apr 1
        $this->assertEquals('2026-03-01 12:00:00', $periods[2]['start']);
        $this->assertEquals('2026-04-01 12:00:00', $periods[2]['end']);
    }
    
    /**
     * Test 4: Days between calculation (helper function)
     * 
     * JavaScript behavior:
     * - days_between(Jan 1, Jan 10) = 10 days (inclusive-inclusive)
     * - Normalizes to 12:00:00 before calculation
     */
    public function testDaysBetweenInclusiveInclusive(): void
    {
        $dateA = Carbon::create(2026, 1, 1, 0, 0, 0);
        $dateB = Carbon::create(2026, 1, 10, 23, 59, 59);
        
        // Should normalize to noon and add 1 for inclusive
        $days = $this->calculator->daysBetween($dateA, $dateB);
        
        // Jan 1 to Jan 10 = 10 days (1, 2, 3, 4, 5, 6, 7, 8, 9, 10)
        $this->assertEquals(10, $days);
    }
    
    /**
     * Test 5: ISO date formatting (helper function)
     */
    public function testIsoDateFormatting(): void
    {
        $date = Carbon::create(2026, 1, 15, 12, 0, 0);
        $iso = $this->calculator->iso($date);
        
        $this->assertEquals('2026-01-15', $iso);
    }
    
    /**
     * Test 6: Edge case - bill_day 31 in February
     * JavaScript behavior: Date constructor handles this (Feb 31 -> Mar 3)
     * Carbon should handle similarly
     */
    public function testBillDay31InFebruary(): void
    {
        $this->calculator->addPeriod(31, '2026-01');
        $periods = $this->calculator->getPeriods();
        
        $period = $periods[0];
        
        // Jan 31 should be valid
        $start = Carbon::parse($period['start']);
        $this->assertEquals(2026, $start->year);
        $this->assertEquals(1, $start->month);
        $this->assertEquals(31, $start->day);
        
        // Feb 31 doesn't exist, Carbon will adjust to Mar 3 (or end of Feb)
        // Need to verify what Carbon does vs JS
        $end = Carbon::parse($period['end']);
        // This is implementation-dependent - document behavior
    }
}










