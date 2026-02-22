<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingCalculatorPeriodToPeriod;
use Carbon\Carbon;

/**
 * UNIT 2.2: Reading Normalization - Parity Tests
 * 
 * Tests PHP calculator reading validation against JavaScript behavior
 */
class BillingCalculatorReadingNormalizationTest extends TestCase
{
    private $calculator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCalculatorPeriodToPeriod();
    }
    
    /**
     * Test 1: Reading monotonicity - valid readings (increasing)
     * 
     * JavaScript behavior:
     * - Reading 1: 1000 L
     * - Reading 2: 2000 L (> 1000) -> ACCEPTED
     * - Reading 3: 3000 L (> 2000) -> ACCEPTED
     */
    public function testReadingMonotonicityValid(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        $this->calculator->addReading(0, '2026-01-20', 3000.0);
        
        // Should not throw
        $this->calculator->validateReadings();
        
        $this->assertTrue(true, 'Valid monotonic readings accepted');
    }
    
    /**
     * Test 2: Reading monotonicity - equal readings (invalid)
     * 
     * JavaScript behavior:
     * - Reading 1: 1000 L
     * - Reading 2: 1000 L (== 1000) -> REJECTED
     * - Error: "cannot be lower than or equal to"
     */
    public function testReadingMonotonicityEqualRejected(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 1000.0); // Equal - should be rejected
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be lower than or equal to');
        
        $this->calculator->validateReadings();
    }
    
    /**
     * Test 3: Reading monotonicity - decreasing readings (invalid)
     * 
     * JavaScript behavior:
     * - Reading 1: 2000 L
     * - Reading 2: 1000 L (< 2000) -> REJECTED
     */
    public function testReadingMonotonicityDecreasingRejected(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 2000.0);
        $this->calculator->addReading(0, '2026-01-15', 1000.0); // Decreasing - should be rejected
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be lower than or equal to');
        
        $this->calculator->validateReadings();
    }
    
    /**
     * Test 4: Period opening constraint - valid (first reading > previous closing)
     * 
     * JavaScript behavior:
     * - Period 1 closing: 2000 L
     * - Period 2 first reading: 2500 L (> 2000) -> ACCEPTED
     */
    public function testPeriodOpeningConstraintValid(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        // Manually set closing for Period 1 (simulating calculation)
        $periods = $this->calculator->getPeriods();
        $periods[0]['closing'] = 2000.0;
        $this->calculator->setPeriods($periods);
        
        // Period 2 - first reading > Period 1 closing
        $this->calculator->addReading(1, '2026-02-10', 2500.0);
        
        // Should not throw
        $this->calculator->validateReadings();
        
        $this->assertTrue(true, 'Valid period opening constraint accepted');
    }
    
    /**
     * Test 5: Period opening constraint - invalid (first reading <= previous closing)
     * 
     * JavaScript behavior:
     * - Period 1 closing: 2000 L
     * - Period 2 first reading: 1500 L (<= 2000) -> REJECTED
     * - Error: "cannot be lower than or equal to the period opening reading"
     */
    public function testPeriodOpeningConstraintInvalid(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        // Manually set closing for Period 1
        $periods = $this->calculator->getPeriods();
        $periods[0]['closing'] = 2000.0;
        $this->calculator->setPeriods($periods);
        
        // Period 2 - first reading <= Period 1 closing (invalid)
        $this->calculator->addReading(1, '2026-02-10', 1500.0);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be lower than or equal to the period opening reading');
        
        $this->calculator->validateReadings();
    }
    
    /**
     * Test 6: Period opening constraint - equal reading (invalid)
     * 
     * JavaScript behavior:
     * - Period 1 closing: 2000 L
     * - Period 2 first reading: 2000 L (== 2000) -> REJECTED
     */
    public function testPeriodOpeningConstraintEqualRejected(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        // Manually set closing for Period 1
        $periods = $this->calculator->getPeriods();
        $periods[0]['closing'] = 2000.0;
        $this->calculator->setPeriods($periods);
        
        // Period 2 - first reading == Period 1 closing (invalid)
        $this->calculator->addReading(1, '2026-02-10', 2000.0);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be lower than or equal to the period opening reading');
        
        $this->calculator->validateReadings();
    }
    
    /**
     * Test 7: Missing/null readings - should be ignored
     * 
     * JavaScript behavior:
     * - Only readings with date AND value !== null are validated
     * - Null readings are filtered out before validation
     */
    public function testMissingNullReadingsIgnored(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        // Add reading with null date (should be ignored)
        $this->calculator->addReading(0, null, 1000.0);
        
        // Add reading with null value (should be ignored)
        $this->calculator->addReading(0, '2026-01-15', null);
        
        // Add valid reading
        $this->calculator->addReading(0, '2026-01-20', 2000.0);
        
        // Should not throw (null readings are ignored)
        $this->calculator->validateReadings();
        
        $this->assertTrue(true, 'Null readings are ignored in validation');
    }
    
    /**
     * Test 8: Error message format matches JavaScript
     * 
     * JavaScript error format:
     * "Reading value (X L on YYYY-MM-DD) cannot be lower than or equal to a previous reading (Z L on YYYY-MM-DD). Please enter a value greater than Z L."
     */
    public function testErrorMessageFormatMatchesJS(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        
        $this->calculator->addReading(0, '2026-01-10', 2000.0);
        $this->calculator->addReading(0, '2026-01-15', 1000.0); // Decreasing
        
        try {
            $this->calculator->validateReadings();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            
            // Verify error message contains key phrases matching JS format
            $this->assertStringContainsString('Reading value', $message);
            $this->assertStringContainsString('cannot be lower than or equal to', $message);
            $this->assertStringContainsString('previous reading', $message);
            $this->assertStringContainsString('Please enter a value greater than', $message);
            $this->assertStringContainsString('L on', $message);
        }
    }
    
    /**
     * Test 9: Period opening error message format matches JavaScript
     * 
     * JavaScript error format:
     * "Reading value (X L on YYYY-MM-DD) cannot be lower than or equal to the period opening reading (Z L). This period opens at Z L (from previous period's closing reading). Please enter a value greater than Z L."
     */
    public function testPeriodOpeningErrorMessageFormatMatchesJS(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        $periods = $this->calculator->getPeriods();
        $periods[0]['closing'] = 2000.0;
        $this->calculator->setPeriods($periods);
        
        // Period 2 - invalid opening
        $this->calculator->addReading(1, '2026-02-10', 1500.0);
        
        try {
            $this->calculator->validateReadings();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            
            // Verify error message contains key phrases matching JS format
            $this->assertStringContainsString('Reading value', $message);
            $this->assertStringContainsString('cannot be lower than or equal to the period opening reading', $message);
            $this->assertStringContainsString('This period opens at', $message);
            $this->assertStringContainsString('from previous period\'s closing reading', $message);
            $this->assertStringContainsString('Please enter a value greater than', $message);
        }
    }
    
    /**
     * Test 10: Cross-period monotonicity (readings across periods)
     * 
     * JavaScript behavior:
     * - All readings across all periods must be monotonic
     * - Period boundaries don't break monotonicity requirement
     */
    public function testCrossPeriodMonotonicity(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        
        // Period 2 - must be > Period 1's last reading
        $this->calculator->addReading(1, '2026-02-10', 2500.0); // > 2000
        $this->calculator->addReading(1, '2026-02-15', 3000.0);
        
        // Should not throw
        $this->calculator->validateReadings();
        
        $this->assertTrue(true, 'Cross-period monotonicity validated');
    }
    
    /**
     * Test 11: Cross-period monotonicity violation
     * 
     * JavaScript behavior:
     * - Period 1 last reading: 2000 L
     * - Period 2 first reading: 1500 L (< 2000) -> REJECTED
     */
    public function testCrossPeriodMonotonicityViolation(): void
    {
        $this->calculator->addPeriod(15, '2026-01');
        $this->calculator->addPeriod(15, '2026-01');
        
        // Period 1
        $this->calculator->addReading(0, '2026-01-10', 1000.0);
        $this->calculator->addReading(0, '2026-01-15', 2000.0);
        
        // Period 2 - violates monotonicity (1500 < 2000)
        $this->calculator->addReading(1, '2026-02-10', 1500.0);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be lower than or equal to a previous reading');
        
        $this->calculator->validateReadings();
    }
}










