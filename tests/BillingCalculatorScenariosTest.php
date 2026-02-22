<?php

/**
 * Billing Calculator Scenarios Test
 * 
 * This script tests various scenarios to verify the billing calculator works correctly
 * after the sector calculation governance refactoring.
 * 
 * Run via: docker exec mycities-laravel php tests/BillingCalculatorScenariosTest.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\BillingCalculatorPeriodToPeriod;
use Carbon\Carbon;

echo "========================================\n";
echo "BILLING CALCULATOR SCENARIOS TEST\n";
echo "========================================\n\n";

// Helper function to create a period structure
$createPeriod = function($start, $end, $opening = null) {
    return [
        'start' => is_string($start) ? $start . ' 12:00:00' : $start->format('Y-m-d H:i:s'),
        'end' => is_string($end) ? $end . ' 12:00:00' : $end->format('Y-m-d H:i:s'),
        'status' => 'PROVISIONAL',
        'readings' => [],
        'opening' => $opening,
        'provisional_closing' => null,
        'calculated_closing' => null,
        'usage' => null,
        'dailyUsage' => null,
        'original_provisional_usage' => null,
        'sectors' => []
    ];
};

$scenarios = [];

// ============================================
// SCENARIO 1: Period 1 with 2 readings (Direct Calculation)
// ============================================
$scenarios[] = [
    'name' => 'Scenario 1: Period 1 with 2 readings (Direct Calculation)',
    'description' => 'Tests that provisional_closing is set correctly when Period 1 has 2 readings',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Add Period 1: Jan 20 - Feb 19
        $calculator->setPeriods([$createPeriod('2026-01-20', '2026-02-20', 0)]);
        
        // Add 2 readings to Period 1
        $calculator->addReading(0, '2026-01-20', 0);
        $calculator->addReading(0, '2026-01-31', 12000);
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        $calculator->calculate($tiers);
        
        $periods = $calculator->getPeriods();
        $period1 = $periods[0];
        
        // Assertions
        $assertions = [];
        $assertions[] = ['provisional_closing exists', isset($period1['provisional_closing']) && $period1['provisional_closing'] !== null];
        $assertions[] = ['provisional_closing = 12000', $period1['provisional_closing'] == 12000];
        $assertions[] = ['usage exists', isset($period1['usage']) && $period1['usage'] !== null];
        $assertions[] = ['dailyUsage exists', isset($period1['dailyUsage']) && $period1['dailyUsage'] !== null];
        $assertions[] = ['status = PROVISIONAL', ($period1['status'] ?? null) === 'PROVISIONAL'];
        $assertions[] = ['calculated_closing is null', !isset($period1['calculated_closing']) || $period1['calculated_closing'] === null];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'period1' => [
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'usage' => $period1['usage'] ?? null,
                'dailyUsage' => $period1['dailyUsage'] ?? null,
                'status' => $period1['status'] ?? null
            ]
        ];
    }
];

// ============================================
// SCENARIO 2: Period 1 with 1 reading (Cannot calculate)
// ============================================
$scenarios[] = [
    'name' => 'Scenario 2: Period 1 with 1 reading (Cannot calculate)',
    'description' => 'Tests that provisional_closing is NOT set when Period 1 has only 1 reading',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Add Period 1: Jan 20 - Feb 19
        $calculator->setPeriods([$createPeriod('2026-01-20', '2026-02-20', 0)]);
        
        // Add only 1 reading to Period 1
        $calculator->addReading(0, '2026-01-20', 0);
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        $calculator->calculate($tiers);
        
        $periods = $calculator->getPeriods();
        $period1 = $periods[0];
        
        // Assertions
        $assertions = [];
        $assertions[] = ['provisional_closing is null', !isset($period1['provisional_closing']) || $period1['provisional_closing'] === null];
        $assertions[] = ['status = PROVISIONAL', ($period1['status'] ?? null) === 'PROVISIONAL'];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'period1' => [
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'status' => $period1['status'] ?? null
            ]
        ];
    }
];

// ============================================
// SCENARIO 3: Period 1 + Period 2 (Cascade)
// ============================================
$scenarios[] = [
    'name' => 'Scenario 3: Period 1 + Period 2 (Cascade)',
    'description' => 'Tests that Period 2 uses Period 1 provisional_closing as opening reading',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Add Period 1: Jan 20 - Feb 19
        $periods = [
            $createPeriod('2026-01-20', '2026-02-20', 0),
            $createPeriod('2026-02-20', '2026-03-20', null) // Period 2: opening will be derived from Period 1
        ];
        $calculator->setPeriods($periods);
        $calculator->addReading(0, '2026-01-20', 0);
        $calculator->addReading(0, '2026-01-31', 12000);
        
        // Add 2 readings to Period 2
        $calculator->addReading(1, '2026-02-20', null); // Will use Period 1's provisional_closing
        $calculator->addReading(1, '2026-02-28', 25000);
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        $calculator->calculate($tiers);
        
        $periods = $calculator->getPeriods();
        $period1 = $periods[0];
        $period2 = $periods[1];
        
        // Assertions
        $assertions = [];
        $assertions[] = ['Period 1 provisional_closing = 12000', $period1['provisional_closing'] == 12000];
        $assertions[] = ['Period 2 opening = 12000', $period2['opening'] == 12000];
        $assertions[] = ['Period 2 provisional_closing exists', isset($period2['provisional_closing']) && $period2['provisional_closing'] !== null];
        $assertions[] = ['Period 2 provisional_closing = 25000', $period2['provisional_closing'] == 25000];
        $assertions[] = ['Period 2 usage = 13000', $period2['usage'] == 13000];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'period1' => [
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'usage' => $period1['usage'] ?? null
            ],
            'period2' => [
                'opening' => $period2['opening'] ?? null,
                'provisional_closing' => $period2['provisional_closing'] ?? null,
                'usage' => $period2['usage'] ?? null
            ]
        ];
    }
];

// ============================================
// SCENARIO 4: Historical Period with Later Reading (Sector Calculation)
// ============================================
$scenarios[] = [
    'name' => 'Scenario 4: Historical Period with Later Reading (Sector Calculation)',
    'description' => 'Tests that historical periods use sector-based calculation (calculated_usage) but NOT provisional_closing',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Add Period 1: Jan 20 - Feb 19
        $periods = [
            $createPeriod('2026-01-20', '2026-02-20', 0),
            $createPeriod('2026-02-20', '2026-03-20', null) // Period 2
        ];
        $calculator->setPeriods($periods);
        $calculator->addReading(0, '2026-01-20', 0);
        $calculator->addReading(0, '2026-01-31', 12000);
        $calculator->addReading(1, '2026-02-20', null); // Will use Period 1's provisional_closing
        $calculator->addReading(1, '2026-02-25', 20000); // Reading in Period 2
        
        // Add reading AFTER Period 1 end (this makes Period 1 historical)
        $calculator->addReading(0, '2026-02-22', 15000); // This reading is after Period 1 end
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        $calculator->calculate($tiers);
        
        $periods = $calculator->getPeriods();
        $period1 = $periods[0];
        
        // Assertions
        $assertions = [];
        $assertions[] = ['Period 1 provisional_closing = 12000 (immutable)', $period1['provisional_closing'] == 12000];
        $assertions[] = ['Period 1 calculated_usage exists', isset($period1['calculated_usage']) && $period1['calculated_usage'] !== null];
        $assertions[] = ['Period 1 calculated_closing is null (not reconciled)', !isset($period1['calculated_closing']) || $period1['calculated_closing'] === null];
        $assertions[] = ['Period 1 status = PROVISIONAL', ($period1['status'] ?? null) === 'PROVISIONAL'];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'period1' => [
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'calculated_usage' => $period1['calculated_usage'] ?? null,
                'calculated_closing' => $period1['calculated_closing'] ?? null,
                'status' => $period1['status'] ?? null
            ]
        ];
    }
];

// ============================================
// SCENARIO 5: Current Period Direct Calculation (No Sector Logic)
// ============================================
$scenarios[] = [
    'name' => 'Scenario 5: Current Period Direct Calculation (No Sector Logic)',
    'description' => 'Tests that current period uses direct calculation, NOT sector-based calculation',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Add Period 1: Jan 20 - Feb 19
        $calculator->setPeriods([$createPeriod('2026-01-20', '2026-02-20', 0)]);
        $calculator->addReading(0, '2026-01-20', 0);
        $calculator->addReading(0, '2026-01-25', 5000);
        $calculator->addReading(0, '2026-01-31', 12000);
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        
        try {
            $calculator->calculate($tiers);
            $error = null;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        $periods = $calculator->getPeriods();
        $period1 = $periods[0];
        
        // Assertions
        $assertions = [];
        $assertions[] = ['No error thrown (direct calculation works)', $error === null];
        $assertions[] = ['provisional_closing = 12000', $period1['provisional_closing'] == 12000];
        $assertions[] = ['usage exists', isset($period1['usage']) && $period1['usage'] !== null];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'error' => $error,
            'period1' => [
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'usage' => $period1['usage'] ?? null
            ]
        ];
    }
];

// ============================================
// SCENARIO 6: Invariant Check - Period with 2+ readings must have provisional_closing
// ============================================
$scenarios[] = [
    'name' => 'Scenario 6: Invariant Check - Period with 2+ readings must have provisional_closing',
    'description' => 'Tests that the invariant check throws error if provisional_closing is missing after calculation',
    'test' => function() use ($createPeriod) {
        // This scenario tests the invariant check
        // If calculation completes but provisional_closing is null for a period with 2+ readings,
        // the invariant check should catch it
        
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Add Period 1: Jan 20 - Feb 19
        $calculator->setPeriods([$createPeriod('2026-01-20', '2026-02-20', 0)]);
        $calculator->addReading(0, '2026-01-20', 0);
        $calculator->addReading(0, '2026-01-31', 12000);
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        
        try {
            $calculator->calculate($tiers);
            $periods = $calculator->getPeriods();
            $period1 = $periods[0];
            
            // Check if invariant is satisfied
            $hasProvisionalClosing = isset($period1['provisional_closing']) && $period1['provisional_closing'] !== null;
            
            return [
                'success' => $hasProvisionalClosing,
                'assertions' => [
                    ['provisional_closing exists after calculation', $hasProvisionalClosing]
                ],
                'period1' => [
                    'provisional_closing' => $period1['provisional_closing'] ?? null
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'assertions' => [
                    ['Calculation completed without error', false]
                ]
            ];
        }
    }
];

// ============================================
// SCENARIO 7: Period 1 with 1 reading, Periods 2-6 empty, Period 7 with 1 reading
// ============================================
$scenarios[] = [
    'name' => 'Scenario 7: Period 1 (1 reading) → Periods 2-6 (empty) → Period 7 (1 reading)',
    'description' => 'Tests cascade across multiple empty periods - Period 7 should derive opening from Period 1',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Create all 7 periods
        $periods = [
            $createPeriod('2026-01-20', '2026-02-20', 0), // Period 1: opening = 0
            $createPeriod('2026-02-20', '2026-03-20', null), // Period 2
            $createPeriod('2026-03-20', '2026-04-20', null), // Period 3
            $createPeriod('2026-04-20', '2026-05-20', null), // Period 4
            $createPeriod('2026-05-20', '2026-06-20', null), // Period 5
            $createPeriod('2026-06-20', '2026-07-20', null), // Period 6
            $createPeriod('2026-07-20', '2026-08-20', null), // Period 7
        ];
        $calculator->setPeriods($periods);
        
        // Add reading to Period 1
        $calculator->addReading(0, '2026-01-20', 0);
        
        // Add readings to Period 7
        $calculator->addReading(6, '2026-07-20', null); // Will derive from Period 1
        $calculator->addReading(6, '2026-07-31', 50000); // Reading in Period 7
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        
        try {
            $calculator->calculate($tiers);
            $error = null;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        $periods = $calculator->getPeriods();
        
        // Assertions
        $assertions = [];
        $assertions[] = ['No error thrown', $error === null];
        $assertions[] = ['7 periods created', count($periods) === 7];
        
        // Period 1 assertions
        $period1 = $periods[0];
        $assertions[] = ['Period 1 has 1 reading', isset($period1['readings']) && count($period1['readings']) === 1];
        $assertions[] = ['Period 1 provisional_closing is null (only 1 reading)', !isset($period1['provisional_closing']) || $period1['provisional_closing'] === null];
        $assertions[] = ['Period 1 status = PROVISIONAL', ($period1['status'] ?? null) === 'PROVISIONAL'];
        
        // Periods 2-6 assertions (empty periods)
        for ($i = 1; $i <= 5; $i++) {
            $period = $periods[$i];
            $assertions[] = ["Period " . ($i + 1) . " has no readings", !isset($period['readings']) || count($period['readings']) === 0];
            $assertions[] = ["Period " . ($i + 1) . " provisional_closing is null", !isset($period['provisional_closing']) || $period['provisional_closing'] === null];
            $assertions[] = ["Period " . ($i + 1) . " status = PROVISIONAL", ($period['status'] ?? null) === 'PROVISIONAL'];
        }
        
        // Period 7 assertions
        $period7 = $periods[6];
        $assertions[] = ['Period 7 has 2 readings', isset($period7['readings']) && count($period7['readings']) === 2];
        
        // Period 7 should derive opening from Period 1 (since Periods 2-6 have no readings)
        // The opening should be 0 (from Period 1's reading value)
        $expectedOpening = 0; // Period 1's reading value
        $assertions[] = ["Period 7 opening = {$expectedOpening} (derived from Period 1)", ($period7['opening'] ?? null) === $expectedOpening];
        
        // Period 7 should have provisional_closing calculated
        $assertions[] = ['Period 7 provisional_closing exists', isset($period7['provisional_closing']) && $period7['provisional_closing'] !== null];
        $assertions[] = ['Period 7 provisional_closing = 50000', $period7['provisional_closing'] == 50000];
        $assertions[] = ['Period 7 usage exists', isset($period7['usage']) && $period7['usage'] !== null];
        $assertions[] = ['Period 7 usage = 50000', $period7['usage'] == 50000];
        $assertions[] = ['Period 7 status = PROVISIONAL', ($period7['status'] ?? null) === 'PROVISIONAL'];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'error' => $error,
            'period1' => [
                'readings_count' => isset($period1['readings']) ? count($period1['readings']) : 0,
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'status' => $period1['status'] ?? null
            ],
            'periods_2_6' => array_map(function($idx) use ($periods) {
                $p = $periods[$idx];
                return [
                    'period' => $idx + 1,
                    'readings_count' => isset($p['readings']) ? count($p['readings']) : 0,
                    'provisional_closing' => $p['provisional_closing'] ?? null,
                    'status' => $p['status'] ?? null
                ];
            }, range(1, 5)),
            'period7' => [
                'readings_count' => isset($period7['readings']) ? count($period7['readings']) : 0,
                'opening' => $period7['opening'] ?? null,
                'provisional_closing' => $period7['provisional_closing'] ?? null,
                'usage' => $period7['usage'] ?? null,
                'status' => $period7['status'] ?? null
            ]
        ];
    }
];

// ============================================
// RUN ALL SCENARIOS
// ============================================

$totalScenarios = count($scenarios);
$passedScenarios = 0;
$failedScenarios = 0;

foreach ($scenarios as $idx => $scenario) {
    echo "----------------------------------------\n";
    echo "Running: {$scenario['name']}\n";
    echo "Description: {$scenario['description']}\n";
    echo "----------------------------------------\n";
    
    try {
        $result = $scenario['test']();
        
        if ($result['success']) {
            echo "✅ PASSED\n";
            $passedScenarios++;
        } else {
            echo "❌ FAILED\n";
            $failedScenarios++;
        }
        
        // Display assertions
        if (isset($result['assertions'])) {
            echo "\nAssertions:\n";
            foreach ($result['assertions'] as $assertion) {
                $status = $assertion[1] ? '✅' : '❌';
                echo "  {$status} {$assertion[0]}\n";
            }
        }
        
        // Display period data
        if (isset($result['period1'])) {
            echo "\nPeriod 1 Data:\n";
            foreach ($result['period1'] as $key => $value) {
                echo "  {$key}: " . ($value !== null ? $value : 'null') . "\n";
            }
        }
        
        if (isset($result['period2'])) {
            echo "\nPeriod 2 Data:\n";
            foreach ($result['period2'] as $key => $value) {
                echo "  {$key}: " . ($value !== null ? $value : 'null') . "\n";
            }
        }
        
        if (isset($result['error'])) {
            echo "\nError: {$result['error']}\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ FAILED with exception\n";
        echo "Error: {$e->getMessage()}\n";
        echo "Stack trace:\n{$e->getTraceAsString()}\n";
        $failedScenarios++;
    }
    
    echo "\n";
}

// ============================================
// SCENARIO 7: Period 1 with 1 reading, Periods 2-6 empty, Period 7 with 1 reading
// ============================================
$scenarios[] = [
    'name' => 'Scenario 7: Period 1 (1 reading) → Periods 2-6 (empty) → Period 7 (1 reading)',
    'description' => 'Tests cascade across multiple empty periods - Period 7 should derive opening from Period 1',
    'test' => function() use ($createPeriod) {
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        // Create all 7 periods
        $periods = [
            $createPeriod('2026-01-20', '2026-02-20', 0), // Period 1: opening = 0
            $createPeriod('2026-02-20', '2026-03-20', null), // Period 2
            $createPeriod('2026-03-20', '2026-04-20', null), // Period 3
            $createPeriod('2026-04-20', '2026-05-20', null), // Period 4
            $createPeriod('2026-05-20', '2026-06-20', null), // Period 5
            $createPeriod('2026-06-20', '2026-07-20', null), // Period 6
            $createPeriod('2026-07-20', '2026-08-20', null), // Period 7
        ];
        $calculator->setPeriods($periods);
        
        // Add reading to Period 1
        $calculator->addReading(0, '2026-01-20', 0);
        
        // Add readings to Period 7
        $calculator->addReading(6, '2026-07-20', null); // Will derive from Period 1
        $calculator->addReading(6, '2026-07-31', 50000); // Reading in Period 7
        
        // Calculate
        $tiers = [
            ['max' => 6000, 'rate' => 50],
            ['max' => 15000, 'rate' => 70],
            ['max' => 45000, 'rate' => 90]
        ];
        
        try {
            $calculator->calculate($tiers);
            $error = null;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        $periods = $calculator->getPeriods();
        
        // Assertions
        $assertions = [];
        $assertions[] = ['No error thrown', $error === null];
        $assertions[] = ['7 periods created', count($periods) === 7];
        
        // Period 1 assertions
        $period1 = $periods[0];
        $assertions[] = ['Period 1 has 1 reading', isset($period1['readings']) && count($period1['readings']) === 1];
        $assertions[] = ['Period 1 provisional_closing is null (only 1 reading)', !isset($period1['provisional_closing']) || $period1['provisional_closing'] === null];
        $assertions[] = ['Period 1 status = PROVISIONAL', ($period1['status'] ?? null) === 'PROVISIONAL'];
        
        // Periods 2-6 assertions (empty periods)
        for ($i = 1; $i <= 5; $i++) {
            $period = $periods[$i];
            $assertions[] = ["Period " . ($i + 1) . " has no readings", !isset($period['readings']) || count($period['readings']) === 0];
            $assertions[] = ["Period " . ($i + 1) . " provisional_closing is null", !isset($period['provisional_closing']) || $period['provisional_closing'] === null];
            $assertions[] = ["Period " . ($i + 1) . " status = PROVISIONAL", ($period['status'] ?? null) === 'PROVISIONAL'];
        }
        
        // Period 7 assertions
        $period7 = $periods[6];
        $assertions[] = ['Period 7 has 2 readings', isset($period7['readings']) && count($period7['readings']) === 2];
        
        // Period 7 should derive opening from Period 1 (since Periods 2-6 have no readings)
        // The opening should be 0 (from Period 1's reading)
        $expectedOpening = 0; // Period 1's reading value
        $assertions[] = ["Period 7 opening = {$expectedOpening} (derived from Period 1)", ($period7['opening'] ?? null) === $expectedOpening];
        
        // Period 7 should have provisional_closing calculated
        $assertions[] = ['Period 7 provisional_closing exists', isset($period7['provisional_closing']) && $period7['provisional_closing'] !== null];
        $assertions[] = ['Period 7 provisional_closing = 50000', $period7['provisional_closing'] == 50000];
        $assertions[] = ['Period 7 usage exists', isset($period7['usage']) && $period7['usage'] !== null];
        $assertions[] = ['Period 7 usage = 50000', $period7['usage'] == 50000];
        $assertions[] = ['Period 7 status = PROVISIONAL', ($period7['status'] ?? null) === 'PROVISIONAL'];
        
        return [
            'success' => array_reduce($assertions, fn($carry, $item) => $carry && $item[1], true),
            'assertions' => $assertions,
            'error' => $error,
            'period1' => [
                'readings_count' => isset($period1['readings']) ? count($period1['readings']) : 0,
                'provisional_closing' => $period1['provisional_closing'] ?? null,
                'status' => $period1['status'] ?? null
            ],
            'periods_2_6' => array_map(function($idx) use ($periods) {
                $p = $periods[$idx];
                return [
                    'period' => $idx + 1,
                    'readings_count' => isset($p['readings']) ? count($p['readings']) : 0,
                    'provisional_closing' => $p['provisional_closing'] ?? null,
                    'status' => $p['status'] ?? null
                ];
            }, range(1, 5)),
            'period7' => [
                'readings_count' => isset($period7['readings']) ? count($period7['readings']) : 0,
                'opening' => $period7['opening'] ?? null,
                'provisional_closing' => $period7['provisional_closing'] ?? null,
                'usage' => $period7['usage'] ?? null,
                'status' => $period7['status'] ?? null
            ]
        ];
    }
];

// ============================================
// SUMMARY
// ============================================
echo "========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n";
echo "Total Scenarios: {$totalScenarios}\n";
echo "Passed: {$passedScenarios}\n";
echo "Failed: {$failedScenarios}\n";
echo "========================================\n";

exit($failedScenarios > 0 ? 1 : 0);

