<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingCalculatorPeriodToPeriod;
use Carbon\Carbon;

/**
 * UNIT 2.3: Sector Creation - Parity Tests
 * 
 * Tests PHP calculator sector creation against JavaScript behavior
 */
class BillingCalculatorSectorCreationTest extends TestCase
{
    private $calculator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCalculatorPeriodToPeriod();
    }
    
    /**
     * Test 1: Simple two-reading case
     * 
     * JavaScript behavior:
     * - Reading 1: 2026-01-10, 1000 L
     * - Reading 2: 2026-01-15, 2000 L
     * - Expected: 1 sector
     *   - start_date: 2026-01-10
     *   - end_date: 2026-01-15
     *   - start_reading: 1000 L
     *   - end_reading: 2000 L
     *   - sector_usage: 1000 L
     *   - sector_days: 6 days (inclusive-inclusive: 10, 11, 12, 13, 14, 15)
     *   - daily_usage: 1000 / 6
     */
    public function testSimpleTwoReadingCase(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors, 'Should create 1 sector from 2 readings');
        
        $sector = $sectors[0];
        
        // Verify sector structure
        $this->assertEquals(1, $sector['sector_id']);
        $this->assertEquals('2026-01-10', $sector['start_date']);
        $this->assertEquals('2026-01-15', $sector['end_date']);
        $this->assertEquals(1000.0, $sector['start_reading']);
        $this->assertEquals(2000.0, $sector['end_reading']);
        $this->assertEquals(1000.0, $sector['sector_usage']);
        $this->assertEquals(6, $sector['sector_days'], 'Jan 10 to Jan 15 = 6 days (inclusive-inclusive)');
        $this->assertEquals(1000.0 / 6, $sector['daily_usage'], '', 0.0001);
        $this->assertEquals(false, $sector['crosses_period']);
    }
    
    /**
     * Test 2: Multiple readings within same period
     * 
     * JavaScript behavior:
     * - Reading 1: 2026-01-10, 1000 L
     * - Reading 2: 2026-01-15, 2000 L
     * - Reading 3: 2026-01-20, 3000 L
     * - Expected: 2 sectors
     *   - Sector 1: Jan 10 -> Jan 15 (1000 L -> 2000 L, 1000 L usage, 6 days)
     *   - Sector 2: Jan 16 -> Jan 20 (2000 L -> 3000 L, 1000 L usage, 5 days)
     */
    public function testMultipleReadingsSamePeriod(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        $this->calculator->addReading(0, '2026-01-20', 3000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(2, $sectors, 'Should create 2 sectors from 3 readings');
        
        // Sector 1: Jan 10 -> Jan 15
        $sector1 = $sectors[0];
        $this->assertEquals(1, $sector1['sector_id']);
        $this->assertEquals('2026-01-10', $sector1['start_date']);
        $this->assertEquals('2026-01-15', $sector1['end_date']);
        $this->assertEquals(1000.0, $sector1['start_reading']);
        $this->assertEquals(2000.0, $sector1['end_reading']);
        $this->assertEquals(1000.0, $sector1['sector_usage']);
        $this->assertEquals(6, $sector1['sector_days']);
        
        // Sector 2: Jan 16 -> Jan 20 (earlier.date + 1 day)
        $sector2 = $sectors[1];
        $this->assertEquals(2, $sector2['sector_id']);
        $this->assertEquals('2026-01-16', $sector2['start_date'], 'Subsequent sectors start at earlier.date + 1 day');
        $this->assertEquals('2026-01-20', $sector2['end_date']);
        $this->assertEquals(2000.0, $sector2['start_reading']);
        $this->assertEquals(3000.0, $sector2['end_reading']);
        $this->assertEquals(1000.0, $sector2['sector_usage']);
        $this->assertEquals(5, $sector2['sector_days'], 'Jan 16 to Jan 20 = 5 days (inclusive-inclusive: 16, 17, 18, 19, 20)');
    }
    
    /**
     * Test 3: Readings with gaps (multi-day)
     * 
     * JavaScript behavior:
     * - Reading 1: 2026-01-01, 1000 L
     * - Reading 2: 2026-01-10, 2000 L (9 days later)
     * - Expected: 1 sector
     *   - start_date: 2026-01-01
     *   - end_date: 2026-01-10
     *   - sector_days: 10 days (inclusive-inclusive: 1, 2, 3, ..., 10)
     */
    public function testReadingsWithGaps(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-01', 1000.0);
        $this->calculator->addReading(0, '2026-01-10', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors);
        
        $sector = $sectors[0];
        $this->assertEquals('2026-01-01', $sector['start_date']);
        $this->assertEquals('2026-01-10', $sector['end_date']);
        $this->assertEquals(10, $sector['sector_days'], 'Jan 1 to Jan 10 = 10 days (inclusive-inclusive)');
    }
    
    /**
     * Test 4: Edge case - first sector opening reading
     * 
     * JavaScript behavior:
     * - First sector uses earlier.date as start_date (not +1 day)
     * - Subsequent sectors use earlier.date + 1 day
     */
    public function testFirstSectorOpeningReading(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        $this->calculator->addReading(0, '2026-01-20', 3000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        // First sector should start at earlier.date (not +1 day)
        $this->assertEquals('2026-01-10', $sectors[0]['start_date'], 'First sector starts at earlier.date');
        $this->assertEquals(1000.0, $sectors[0]['start_reading'], 'First sector uses earlier.value as start_reading');
        
        // Subsequent sector should start at earlier.date + 1 day
        $this->assertEquals('2026-01-16', $sectors[1]['start_date'], 'Subsequent sectors start at earlier.date + 1 day');
        $this->assertEquals(2000.0, $sectors[1]['start_reading'], 'Subsequent sectors use earlier.value as start_reading');
    }
    
    /**
     * Test 5: Readings spanning several days (no splitting)
     * 
     * JavaScript behavior:
     * - Reading 1: 2026-01-01, 1000 L
     * - Reading 2: 2026-01-31, 2000 L
     * - Expected: 1 sector spanning entire period (no splitting in this unit)
     *   - start_date: 2026-01-01
     *   - end_date: 2026-01-31
     *   - sector_days: 31 days (inclusive-inclusive)
     */
    public function testReadingsSpanningSeveralDays(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-01', 1000.0);
        $this->calculator->addReading(0, '2026-01-31', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors);
        
        $sector = $sectors[0];
        $this->assertEquals('2026-01-01', $sector['start_date']);
        $this->assertEquals('2026-01-31', $sector['end_date']);
        $this->assertEquals(31, $sector['sector_days'], 'Jan 1 to Jan 31 = 31 days (inclusive-inclusive)');
        
        // Verify no splitting occurred (explicit exclusion)
        $this->assertEquals(false, $sector['crosses_period'], 'Sector should not be marked as crossing period');
        $this->assertEmpty($sector['sub_sectors'], 'Sector should have no sub-sectors');
    }
    
    /**
     * Test 6: Negative usage prevention
     * 
     * JavaScript behavior:
     * - Reading 1: 2026-01-10, 2000 L
     * - Reading 2: 2026-01-15, 1000 L (< 2000) -> REJECTED
     * - Error: "Sector usage cannot be negative"
     */
    public function testNegativeUsagePrevention(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 2000.0);
        $this->calculator->addReading(0, '2026-01-15', 1000.0); // Decreasing reading
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sector usage cannot be negative');
        
        $this->calculator->createSectorsFromReadings();
    }
    
    /**
     * Test 7: Handling of null / skipped readings
     * 
     * JavaScript behavior:
     * - Null date or value readings are filtered out before sector creation
     * - Only readings with both date and value are used
     */
    public function testNullSkippedReadings(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add reading with null date (should be ignored)
        $this->calculator->addReading(0, null, 1000.0);
        
        // Add valid reading
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        
        // Add reading with null value (should be ignored)
        $this->calculator->addReading(0, '2026-01-15', null);
        
        // Add another valid reading
        $this->calculator->addReading(0, '2026-01-20', 2000.0);
        
        // Should create 1 sector from 2 valid readings
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(1, $sectors, 'Should create 1 sector from 2 valid readings (null readings ignored)');
        
        $sector = $sectors[0];
        $this->assertEquals('2026-01-10', $sector['start_date']);
        $this->assertEquals('2026-01-20', $sector['end_date']);
        $this->assertEquals(1000.0, $sector['start_reading']);
        $this->assertEquals(2000.0, $sector['end_reading']);
    }
    
    /**
     * Test 8: Sector ordering (chronological)
     * 
     * JavaScript behavior:
     * - Sectors are created in chronological order
     * - Sector IDs increment: 1, 2, 3, ...
     */
    public function testSectorOrdering(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        $this->calculator->addReading(0, '2026-01-20', 3000.0);
        $this->calculator->addReading(0, '2026-01-25', 4000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $this->assertCount(3, $sectors);
        
        // Verify sector IDs increment
        $this->assertEquals(1, $sectors[0]['sector_id']);
        $this->assertEquals(2, $sectors[1]['sector_id']);
        $this->assertEquals(3, $sectors[2]['sector_id']);
        
        // Verify chronological ordering
        $this->assertEquals('2026-01-10', $sectors[0]['start_date']);
        $this->assertEquals('2026-01-16', $sectors[1]['start_date']);
        $this->assertEquals('2026-01-21', $sectors[2]['start_date']);
    }
    
    /**
     * Test 9: Daily usage calculation
     * 
     * JavaScript behavior:
     * - daily_usage = sector_usage / sector_days (if sector_days > 0)
     * - daily_usage = 0 (if sector_days = 0)
     */
    public function testDailyUsageCalculation(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // 10-day gap with 1000 L usage
        $this->calculator->addReading(0, '2026-01-01', 1000.0);
        $this->calculator->addReading(0, '2026-01-11', 2000.0);
        
        $sectors = $this->calculator->createSectorsFromReadings();
        
        $sector = $sectors[0];
        $expectedDailyUsage = 1000.0 / 11; // 11 days (Jan 1 to Jan 11, inclusive-inclusive)
        
        $this->assertEquals($expectedDailyUsage, $sector['daily_usage'], '', 0.0001);
    }
    
    /**
     * Test 10: Empty readings (returns empty array)
     * 
     * JavaScript behavior:
     * - If all_readings.length < 2, return []
     * - Need at least 2 readings to create 1 sector
     */
    public function testEmptyReadings(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // No readings
        $sectors = $this->calculator->createSectorsFromReadings();
        $this->assertEmpty($sectors, 'No readings should return empty array');
        
        // Only 1 reading
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $sectors = $this->calculator->createSectorsFromReadings();
        $this->assertEmpty($sectors, 'Only 1 reading should return empty array (need 2+ for sectors)');
    }
}










