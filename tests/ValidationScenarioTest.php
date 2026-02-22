<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BillingCalculatorPeriodToPeriod;

echo "========================================\n";
echo "VALIDATION SCENARIO TEST\n";
echo "Period 1 (1 reading) → Periods 2-6 (empty) → Period 7 (1 reading)\n";
echo "========================================\n\n";

$calculator = new BillingCalculatorPeriodToPeriod();

// Create all 7 periods
$periods = [
    ['start' => '2026-01-20', 'end' => '2026-02-20', 'readings' => []], // Period 1
    ['start' => '2026-02-20', 'end' => '2026-03-20', 'readings' => []], // Period 2
    ['start' => '2026-03-20', 'end' => '2026-04-20', 'readings' => []], // Period 3
    ['start' => '2026-04-20', 'end' => '2026-05-20', 'readings' => []], // Period 4
    ['start' => '2026-05-20', 'end' => '2026-06-20', 'readings' => []], // Period 5
    ['start' => '2026-06-20', 'end' => '2026-07-20', 'readings' => []], // Period 6
    ['start' => '2026-07-20', 'end' => '2026-08-20', 'readings' => []], // Period 7
];

$calculator->setPeriods($periods);

// Add reading to Period 1
$calculator->addReading(0, '2026-01-20', 0);

// Add reading to Period 7
$calculator->addReading(6, '2026-07-31', 50000);

// Set tiers for reconciliation
$tiers = [
    ['max' => 6000, 'rate' => 50],
    ['max' => 15000, 'rate' => 70],
    ['max' => 45000, 'rate' => 90]
];

// Run calculation
try {
    $calculator->calculate($tiers);
    $error = null;
} catch (\Exception $e) {
    $error = $e->getMessage();
    echo "ERROR: {$error}\n\n";
}

$periods = $calculator->getPeriods();

// ========================================
// A. PERIOD TABLE
// ========================================
echo "A. PERIOD TABLE\n";
echo str_repeat("=", 120) . "\n";
printf("%-8s | %-12s | %-12s | %-12s | %-15s | %-15s | %-15s\n", 
    "Period", "Status", "Opening", "Closing", "Usage", "Calculated Usage", "Daily Usage");
echo str_repeat("-", 120) . "\n";

foreach ($periods as $idx => $p) {
    $periodNum = $idx + 1;
    $status = $p['status'] ?? 'null';
    $opening = isset($p['opening']) ? number_format($p['opening'], 2) : 'null';
    $closing = isset($p['provisional_closing']) ? number_format($p['provisional_closing'], 2) : 
               (isset($p['calculated_closing']) ? number_format($p['calculated_closing'], 2) . ' (calc)' : 'null');
    $usage = isset($p['usage']) ? number_format($p['usage'], 2) : 'null';
    $calcUsage = isset($p['calculated_usage']) ? number_format($p['calculated_usage'], 2) : 'null';
    $dailyUsage = isset($p['dailyUsage']) ? number_format($p['dailyUsage'], 2) : 
                  (isset($p['calculated_dailyUsage']) ? number_format($p['calculated_dailyUsage'], 2) . ' (calc)' : 'null');
    
    printf("%-8s | %-12s | %-12s | %-12s | %-15s | %-15s | %-15s\n",
        "Period {$periodNum}", $status, $opening, $closing, $usage, $calcUsage, $dailyUsage);
}
echo "\n";

// ========================================
// B. SECTOR AND SUB-SECTOR STRUCTURE
// ========================================
echo "B. SECTOR AND SUB-SECTOR STRUCTURE\n";
echo str_repeat("=", 120) . "\n";

// Get all sectors
$allSectors = [];
foreach ($periods as $idx => $p) {
    $sectors = $calculator->getSectorsForPeriod($idx);
    if (!empty($sectors)) {
        foreach ($sectors as $sector) {
            $sectorId = $sector['sector_id'] ?? 'unknown';
            $subId = $sector['sub_id'] ?? 'unknown';
            $key = "{$sectorId}-{$subId}";
            if (!isset($allSectors[$key])) {
                $allSectors[$key] = [
                    'sector_id' => $sectorId,
                    'sub_id' => $subId,
                    'period_index' => $idx,
                    'start_date' => $sector['start_date'] ?? 'null',
                    'end_date' => $sector['end_date'] ?? 'null',
                    'days_in_period' => $sector['days_in_period'] ?? 0,
                    'usage_in_period' => $sector['usage_in_period'] ?? 0,
                    'start_reading' => $sector['start_reading'] ?? 'null',
                    'end_reading' => $sector['end_reading'] ?? 'null',
                ];
            }
        }
    }
}

if (empty($allSectors)) {
    echo "No sectors found.\n";
} else {
    printf("%-12s | %-12s | %-8s | %-12s | %-12s | %-8s | %-15s | %-12s | %-12s\n",
        "Sector ID", "Sub ID", "Period", "Start Date", "End Date", "Days", "Usage", "Start Read", "End Read");
    echo str_repeat("-", 120) . "\n";
    
    foreach ($allSectors as $sector) {
        printf("%-12s | %-12s | %-8s | %-12s | %-12s | %-8s | %-15s | %-12s | %-12s\n",
            $sector['sector_id'],
            $sector['sub_id'],
            "Period " . ($sector['period_index'] + 1),
            $sector['start_date'],
            $sector['end_date'],
            $sector['days_in_period'],
            number_format($sector['usage_in_period'], 2),
            $sector['start_reading'] !== 'null' ? number_format($sector['start_reading'], 2) : 'null',
            $sector['end_reading'] !== 'null' ? number_format($sector['end_reading'], 2) : 'null'
        );
    }
}
echo "\n";

// ========================================
// C. RECONCILIATION SUMMARY
// ========================================
echo "C. RECONCILIATION SUMMARY\n";
echo str_repeat("=", 120) . "\n";

$reconciledPeriods = [];
foreach ($periods as $idx => $p) {
    if (isset($p['reconciliation']) && $p['reconciliation'] !== null) {
        $recon = $p['reconciliation'];
        $reconciledPeriods[] = [
            'period' => $idx + 1,
            'adjustment_litres' => $recon['adjustment_litres'] ?? 0,
            'adjustment_cost' => $recon['adjustment_cost'] ?? 0,
            'forward_applied' => isset($p['adjustment_brought_forward']) ? 'Yes' : 'No'
        ];
    }
}

if (empty($reconciledPeriods)) {
    echo "No reconciliations performed.\n";
} else {
    printf("%-8s | %-20s | %-20s | %-20s\n",
        "Period", "Adjustment Litres", "Adjustment Cost", "Forward Applied");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($reconciledPeriods as $rec) {
        printf("%-8s | %-20s | %-20s | %-20s\n",
            "Period {$rec['period']}",
            number_format($rec['adjustment_litres'], 2),
            number_format($rec['adjustment_cost'], 2),
            $rec['forward_applied']
        );
    }
}
echo "\n";

// ========================================
// D. VALIDATION CONFIRMATION
// ========================================
echo "D. VALIDATION CONFIRMATION\n";
echo str_repeat("=", 120) . "\n";

$allProvisional = true;
$noCalculated = true;
$integrityPassed = true;

foreach ($periods as $idx => $p) {
    $status = $p['status'] ?? null;
    if ($status !== 'PROVISIONAL') {
        $allProvisional = false;
        echo "❌ Period " . ($idx + 1) . " has status: {$status} (expected PROVISIONAL)\n";
    }
    if ($status === 'CALCULATED') {
        $noCalculated = false;
        echo "❌ Period " . ($idx + 1) . " incorrectly transitioned to CALCULATED\n";
    }
}

// Check if current period (last period) is CALCULATED (should never happen)
$lastPeriodIndex = count($periods) - 1;
$lastPeriod = $periods[$lastPeriodIndex];
if (($lastPeriod['status'] ?? null) === 'CALCULATED') {
    echo "❌ Period " . ($lastPeriodIndex + 1) . " (current period) incorrectly has status CALCULATED (should be PROVISIONAL)\n";
    $noCalculated = false;
}

// Try to verify integrity if method exists
try {
    if (method_exists($calculator, 'verifyIntegrity')) {
        $calculator->verifyIntegrity(true);
        echo "✅ Integrity check passed\n";
    } else {
        echo "⚠️  verifyIntegrity() method not found\n";
    }
} catch (\Exception $e) {
    $integrityPassed = false;
    echo "❌ Integrity check failed: {$e->getMessage()}\n";
}

echo "\n";
echo "SUMMARY:\n";
echo str_repeat("-", 120) . "\n";
echo ($allProvisional ? "✅ " : "❌ ") . "All periods remain PROVISIONAL: " . ($allProvisional ? "YES" : "NO") . "\n";
echo ($noCalculated ? "✅ " : "❌ ") . "No period incorrectly transitioned to CALCULATED: " . ($noCalculated ? "YES" : "NO") . "\n";
echo ($integrityPassed ? "✅ " : "❌ ") . "Integrity check passed: " . ($integrityPassed ? "YES" : "NO") . "\n";

// Additional details
echo "\nADDITIONAL DETAILS:\n";
echo str_repeat("-", 120) . "\n";
foreach ($periods as $idx => $p) {
    $periodNum = $idx + 1;
    $readingsCount = isset($p['readings']) ? count($p['readings']) : 0;
    $hasProvisionalClosing = isset($p['provisional_closing']) && $p['provisional_closing'] !== null;
    $hasCalculatedClosing = isset($p['calculated_closing']) && $p['calculated_closing'] !== null;
    $hasUsage = isset($p['usage']) && $p['usage'] !== null;
    $hasCalculatedUsage = isset($p['calculated_usage']) && $p['calculated_usage'] !== null;
    $isReconciled = isset($p['is_reconciled']) && $p['is_reconciled'] === true;
    
    echo "Period {$periodNum}:\n";
    echo "  - Readings: {$readingsCount}\n";
    echo "  - Provisional Closing: " . ($hasProvisionalClosing ? number_format($p['provisional_closing'], 2) : "null") . "\n";
    echo "  - Calculated Closing: " . ($hasCalculatedClosing ? number_format($p['calculated_closing'], 2) : "null") . "\n";
    echo "  - Usage: " . ($hasUsage ? number_format($p['usage'], 2) : "null") . "\n";
    echo "  - Calculated Usage: " . ($hasCalculatedUsage ? number_format($p['calculated_usage'], 2) : "null") . "\n";
    echo "  - Is Reconciled: " . ($isReconciled ? "Yes" : "No") . "\n";
    echo "  - Is Current Period: " . (($idx === count($periods) - 1) ? "Yes" : "No") . "\n";
    echo "\n";
}

echo "========================================\n";
echo "VALIDATION TEST COMPLETE\n";
echo "========================================\n";

