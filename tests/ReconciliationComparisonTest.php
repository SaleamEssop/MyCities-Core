<?php

/**
 * RECONCILIATION GOVERNANCE - Comparison Test
 * 
 * Simple test script to verify reconciliation governance implementation
 * Run with: php tests/ReconciliationComparisonTest.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\BillingCalculatorPeriodToPeriod;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== RECONCILIATION GOVERNANCE COMPARISON TEST ===\n\n";

// Standard tier structure
$tiers = [
    ['max' => 1000, 'rate' => 10.0],
    ['max' => 3000, 'rate' => 15.0],
    ['max' => 5000, 'rate' => 20.0]
];

// Test Case 1: PROVISIONAL → ACTUAL Transition
echo "Test Case 1: PROVISIONAL → ACTUAL Transition\n";
echo "---------------------------------------------\n";

$calculator = new BillingCalculatorPeriodToPeriod();

// Setup Period 1
$calculator->addPeriod(15, '2026-01');

// Add readings (creates PROVISIONAL period)
$calculator->addReading(0, '2026-01-20', 1000.0);
$calculator->addReading(0, '2026-01-25', 2000.0);

// Calculate (should create PROVISIONAL with usage)
$calculator->calculate($tiers);
$periods = $calculator->getPeriods();
$period1 = $periods[0];

echo "After initial calculation:\n";
echo "  Status: " . $period1['status'] . "\n";
echo "  Usage: " . ($period1['usage'] ?? 'null') . " L\n";
echo "  Original Provisional Usage: " . ($period1['original_provisional_usage'] ?? 'null') . " L\n";
echo "  Reconciliation: " . ($calculator->getReconciliation(0) ? 'Present' : 'None') . "\n\n";

// Add Period 2
$calculator->addPeriod(15, '2026-01');

// Add ACTUAL reading on Period 1 end date (triggers transition)
// Period 1 end is 2026-02-15 (exclusive), so last day is 2026-02-14
$calculator->addReading(0, '2026-02-14', 4500.0);

// Recalculate (should transition to ACTUAL and trigger reconciliation)
$calculator->calculate($tiers);
$periods = $calculator->getPeriods();
$period1 = $periods[0];
$period2 = $periods[1] ?? null;

echo "After transition to ACTUAL:\n";
echo "  Status: " . $period1['status'] . "\n";
echo "  Usage: " . ($period1['usage'] ?? 'null') . " L\n";
echo "  Original Provisional Usage: " . ($period1['original_provisional_usage'] ?? 'null') . " L\n";

$reconciliation = $calculator->getReconciliation(0);
if ($reconciliation !== null) {
    $metadata = $reconciliation['metadata'];
    echo "  Reconciliation Triggered: YES\n";
    echo "  Provisioned Usage: " . number_format($metadata['provisioned_usage'], 2) . " L\n";
    echo "  Calculated Usage: " . number_format($metadata['calculated_usage'], 2) . " L\n";
    echo "  Adjustment Litres: " . number_format($metadata['adjustment_litres'], 2) . " L\n";
    echo "  Adjustment Type: " . $metadata['adjustment_type'] . "\n";
    echo "  Adjustment Cost: R" . number_format($metadata['adjustment_cost'], 2) . "\n";
} else {
    echo "  Reconciliation Triggered: NO\n";
}

if ($period2 !== null) {
    echo "\nPeriod 2 (Next Period):\n";
    echo "  Adjustment Brought Forward: " . ($period2['adjustment_brought_forward'] ?? 'null') . "\n";
    echo "  Reconciliation From Period: " . ($period2['reconciliation_from_period'] ?? 'null') . "\n";
}

echo "\n";

// Test Case 2: No Reconciliation on Same Status
echo "Test Case 2: No Reconciliation on Same Status\n";
echo "---------------------------------------------\n";

$calculator2 = new BillingCalculatorPeriodToPeriod();
$calculator2->addPeriod(15, '2026-01');
$calculator2->addReading(0, '2026-01-20', 1000.0);
$calculator2->addReading(0, '2026-01-25', 2000.0);

// Calculate (should create PROVISIONAL)
$calculator2->calculate($tiers);
$periods2 = $calculator2->getPeriods();
$period1_2 = $periods2[0];

echo "Initial calculation:\n";
echo "  Status: " . $period1_2['status'] . "\n";

// Add another reading (but not on end date, so still PROVISIONAL)
$calculator2->addReading(0, '2026-01-30', 2500.0);

// Recalculate (should remain PROVISIONAL)
$calculator2->calculate($tiers);
$periods2 = $calculator2->getPeriods();
$period1_2 = $periods2[0];

echo "After adding reading (still PROVISIONAL):\n";
echo "  Status: " . $period1_2['status'] . "\n";
echo "  Reconciliation: " . ($calculator2->getReconciliation(0) ? 'Present' : 'None') . "\n\n";

// Test Case 3: Persistence Test
echo "Test Case 3: Reconciliation Persistence\n";
echo "---------------------------------------------\n";

$calculator3 = new BillingCalculatorPeriodToPeriod();
$calculator3->addPeriod(15, '2026-01');
$calculator3->addReading(0, '2026-01-20', 1000.0);
$calculator3->addReading(0, '2026-01-25', 2000.0);
$calculator3->calculate($tiers);

$calculator3->addPeriod(15, '2026-01');
$calculator3->addReading(0, '2026-02-14', 4500.0);
$calculator3->calculate($tiers);

$reconciliation = $calculator3->getReconciliation(0);
if ($reconciliation !== null) {
    echo "Reconciliation exists: YES\n";
    echo "Attempting to persist...\n";
    
    try {
        $reconciliationModel = $calculator3->persistReconciliation(0, 1, 1, 1);
        if ($reconciliationModel !== null) {
            echo "  Persisted successfully!\n";
            echo "  ID: " . $reconciliationModel->id . "\n";
            echo "  Original Estimate: R" . number_format($reconciliationModel->original_estimate, 2) . "\n";
            echo "  Calculated Actual: R" . number_format($reconciliationModel->calculated_actual, 2) . "\n";
            echo "  Adjustment Units: " . number_format($reconciliationModel->adjustment_units, 2) . " L\n";
            echo "  Adjustment Type: " . $reconciliationModel->adjustment_type . "\n";
            echo "  Status: " . $reconciliationModel->status . "\n";
        } else {
            echo "  Persistence returned null\n";
        }
    } catch (\Exception $e) {
        echo "  Persistence failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Reconciliation exists: NO\n";
}

echo "\n=== TEST COMPLETE ===\n";










