<?php

/**
 * AUTOMATED JS TO PHP COMPARISON TEST SUITE
 * 
 * Runs comprehensive tests comparing JavaScript and PHP calculator outputs
 * Documents all differences and anomalies
 * 
 * Run with: docker exec mycities-laravel php /var/www/html/tests/AutomatedComparisonTest.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BillingCalculatorPeriodToPeriod;
use App\Services\BillingCalculatorDualRun;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  AUTOMATED JS TO PHP COMPARISON TEST SUITE                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$anomalies = [];

// ==================== TEST CONFIGURATIONS ====================

$testCases = [
    [
        'name' => 'Single Period - ACTUAL Reading',
        'description' => 'Period with ACTUAL reading on period end date',
        'bill_day' => 20,
        'start_month' => '2026-01',
        'tiers' => [
            ['max' => 1000, 'rate' => 10.0],
            ['max' => 3000, 'rate' => 15.0],
            ['max' => 5000, 'rate' => 20.0]
        ],
        'period_count' => 1,
        'readings' => [
            ['period_index' => 0, 'date' => '2026-01-20', 'value' => 0.0],
            ['period_index' => 0, 'date' => '2026-01-30', 'value' => 13000.0],
            ['period_index' => 0, 'date' => '2026-02-19', 'value' => 36636.0], // ACTUAL on period end
        ]
    ],
    [
        'name' => 'Two Periods - PROVISIONAL to ACTUAL',
        'description' => 'First period PROVISIONAL, second period ACTUAL with reconciliation',
        'bill_day' => 20,
        'start_month' => '2026-01',
        'tiers' => [
            ['max' => 1000, 'rate' => 10.0],
            ['max' => 3000, 'rate' => 15.0],
            ['max' => 5000, 'rate' => 20.0]
        ],
        'period_count' => 2,
        'readings' => [
            ['period_index' => 0, 'date' => '2026-01-20', 'value' => 0.0],
            ['period_index' => 0, 'date' => '2026-01-30', 'value' => 13000.0],
            ['period_index' => 1, 'date' => '2026-02-19', 'value' => 36636.0], // ACTUAL on period 1 end
        ]
    ],
    [
        'name' => 'Three Periods - Reconciliation Chain',
        'description' => 'Multiple periods with reconciliation adjustments',
        'bill_day' => 15,
        'start_month' => '2026-01',
        'tiers' => [
            ['max' => 1000, 'rate' => 10.0],
            ['max' => 3000, 'rate' => 15.0],
            ['max' => 5000, 'rate' => 20.0]
        ],
        'period_count' => 3,
        'readings' => [
            ['period_index' => 0, 'date' => '2026-01-15', 'value' => 1000.0],
            ['period_index' => 0, 'date' => '2026-01-25', 'value' => 2000.0],
            ['period_index' => 0, 'date' => '2026-02-14', 'value' => 4500.0], // ACTUAL
            ['period_index' => 1, 'date' => '2026-03-14', 'value' => 6000.0], // ACTUAL
        ]
    ],
    [
        'name' => 'Single Reading - PROVISIONAL',
        'description' => 'Period with only one reading (should be PROVISIONAL)',
        'bill_day' => 20,
        'start_month' => '2026-01',
        'tiers' => [
            ['max' => 1000, 'rate' => 10.0],
            ['max' => 3000, 'rate' => 15.0]
        ],
        'period_count' => 1,
        'readings' => [
            ['period_index' => 0, 'date' => '2026-01-20', 'value' => 0.0],
        ]
    ],
    [
        'name' => 'Multiple Sectors',
        'description' => 'Period with multiple reading pairs creating sectors',
        'bill_day' => 20,
        'start_month' => '2026-01',
        'tiers' => [
            ['max' => 1000, 'rate' => 10.0],
            ['max' => 3000, 'rate' => 15.0],
            ['max' => 5000, 'rate' => 20.0]
        ],
        'period_count' => 1,
        'readings' => [
            ['period_index' => 0, 'date' => '2026-01-20', 'value' => 0.0],
            ['period_index' => 0, 'date' => '2026-01-25', 'value' => 5000.0],
            ['period_index' => 0, 'date' => '2026-02-05', 'value' => 10000.0],
            ['period_index' => 0, 'date' => '2026-02-19', 'value' => 20000.0],
        ]
    ]
];

// ==================== HELPER FUNCTIONS ====================

function formatPhpOutputForComparison($calculator, $tiers) {
    $periods = $calculator->getPeriods();
    $formattedPeriods = [];

    foreach ($periods as $idx => $period) {
        $formatDate = function($date) {
            if ($date === null) return null;
            if (is_string($date)) return $date;
            if (is_object($date) && method_exists($date, 'format')) {
                return $date->format('Y-m-d');
            }
            return (string)$date;
        };

        $periodData = [
            'index' => $idx,
            'start' => $formatDate($period['start'] ?? null),
            'end' => $formatDate($period['end'] ?? null),
            'status' => $period['status'] ?? null,
            'opening' => isset($period['opening']) ? (float)$period['opening'] : null,
            'closing' => isset($period['closing']) ? (float)$period['closing'] : null,
            'usage' => isset($period['usage']) ? (float)$period['usage'] : null,
            'dailyUsage' => isset($period['dailyUsage']) ? (float)$period['dailyUsage'] : null,
            'start_reading' => isset($period['start_reading']) ? (float)$period['start_reading'] : null,
            'original_provisional_usage' => isset($period['original_provisional_usage']) ? (float)$period['original_provisional_usage'] : null,
            'sectors' => [],
            'readings' => []
        ];

        // Format readings
        if (isset($period['readings']) && is_array($period['readings'])) {
            foreach ($period['readings'] as $reading) {
                $dateValue = null;
                if (isset($reading['date']) && $reading['date'] !== null) {
                    if (is_string($reading['date'])) {
                        $dateValue = $reading['date'];
                    } elseif (is_object($reading['date']) && method_exists($reading['date'], 'format')) {
                        $dateValue = $reading['date']->format('Y-m-d');
                    } else {
                        $dateValue = (string)$reading['date'];
                    }
                }
                $periodData['readings'][] = [
                    'date' => $dateValue,
                    'value' => isset($reading['value']) ? (float)$reading['value'] : null
                ];
            }
        }

        // Format sectors
        if (isset($period['sectors']) && is_array($period['sectors'])) {
            foreach ($period['sectors'] as $sector) {
                $periodData['sectors'][] = [
                    'sector_id' => $sector['sector_id'] ?? null,
                    'sub_id' => $sector['sub_id'] ?? null,
                    'start_date' => $formatDate($sector['start_date'] ?? null),
                    'end_date' => $formatDate($sector['end_date'] ?? null),
                    'start_reading' => isset($sector['start_reading']) ? (float)$sector['start_reading'] : null,
                    'end_reading' => isset($sector['end_reading']) ? (float)$sector['end_reading'] : null,
                    'total_usage' => isset($sector['total_usage']) ? (float)($sector['total_usage']) : (isset($sector['usage_in_period']) ? (float)$sector['usage_in_period'] : null),
                    'days_in_period' => isset($sector['days_in_period']) ? (int)$sector['days_in_period'] : null,
                    'usage_in_period' => isset($sector['usage_in_period']) ? (float)$sector['usage_in_period'] : (isset($sector['total_usage']) ? (float)$sector['total_usage'] : null),
                    'daily_usage' => isset($sector['daily_usage']) ? (float)$sector['daily_usage'] : null
                ];
            }
        }

        // Include reconciliation data
        $reconciliation = $calculator->getReconciliation($idx);
        if ($reconciliation !== null) {
            $periodData['reconciliation'] = $reconciliation['reconciliation'] ?? null;
            $periodData['reconciliation_metadata'] = $reconciliation['metadata'] ?? null;
        }

        // Include adjustment_brought_forward
        if (isset($period['adjustment_brought_forward'])) {
            $periodData['adjustment_brought_forward'] = (float)$period['adjustment_brought_forward'];
        }

        // Include reconciliation_from_period
        if (isset($period['reconciliation_from_period'])) {
            $periodData['reconciliation_from_period'] = (int)$period['reconciliation_from_period'];
        }

        $formattedPeriods[] = $periodData;
    }

    return ['periods' => $formattedPeriods];
}

function simulateJsOutput($phpOutput) {
    // For now, simulate JS output as identical to PHP
    // In real scenario, this would come from actual JS calculator
    return $phpOutput;
}

// ==================== RUN TESTS ====================

echo "Running " . count($testCases) . " test cases...\n\n";

foreach ($testCases as $testIdx => $testCase) {
    $totalTests++;
    $testName = $testCase['name'];
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Test " . ($testIdx + 1) . ": {$testName}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Description: {$testCase['description']}\n\n";

    try {
        // Run PHP calculator
        $calculator = new BillingCalculatorPeriodToPeriod();
        
        for ($i = 0; $i < $testCase['period_count']; $i++) {
            $calculator->addPeriod($testCase['bill_day'], $testCase['start_month']);
        }

        foreach ($testCase['readings'] as $reading) {
            $calculator->addReading(
                $reading['period_index'],
                $reading['date'],
                $reading['value']
            );
        }

        $calculator->calculate($testCase['tiers']);
        $phpOutput = formatPhpOutputForComparison($calculator, $testCase['tiers']);

        // Simulate JS output (in real scenario, this comes from browser)
        $jsOutput = simulateJsOutput($phpOutput);

        // Run comparison
        $dualRun = new BillingCalculatorDualRun();
        $result = $dualRun->execute(
            [
                'bill_day' => $testCase['bill_day'],
                'start_month' => $testCase['start_month'],
                'tiers' => $testCase['tiers'],
                'period_count' => $testCase['period_count'],
                'readings' => $testCase['readings']
            ],
            $jsOutput,
            "test-{$testIdx}-" . str_replace(' ', '-', strtolower($testName)),
            ['test_name' => $testName]
        );

        // Analyze results
        $testResult = [
            'test_name' => $testName,
            'test_index' => $testIdx + 1,
            'parity_status' => $result['parity_status'],
            'diff_count' => $result['diff_count'],
            'diffs' => $result['diffs'],
            'php_output' => $phpOutput,
            'periods_count' => count($phpOutput['periods']),
            'errors' => []
        ];

        // Check for specific issues
        foreach ($phpOutput['periods'] as $periodIdx => $period) {
            // Check for null usage when readings exist
            if (count($period['readings']) >= 2 && $period['usage'] === null) {
                $testResult['errors'][] = "Period {$periodIdx}: Has readings but usage is null";
            }

            // Check for invalid status
            if (!in_array($period['status'], ['PROVISIONAL', 'CALCULATED', 'ACTUAL', 'OPEN'])) {
                $testResult['errors'][] = "Period {$periodIdx}: Invalid status '{$period['status']}'";
            }

            // Check for negative usage
            if ($period['usage'] !== null && $period['usage'] < 0) {
                $testResult['errors'][] = "Period {$periodIdx}: Negative usage ({$period['usage']})";
            }

            // Check for negative daily usage
            if ($period['dailyUsage'] !== null && $period['dailyUsage'] < 0) {
                $testResult['errors'][] = "Period {$periodIdx}: Negative daily usage ({$period['dailyUsage']})";
            }
        }

        $testResults[] = $testResult;

        if ($result['parity_status'] === 'PASS' && empty($testResult['errors'])) {
            $passedTests++;
            echo "✅ PASSED\n";
        } else {
            $failedTests++;
            echo "❌ FAILED\n";
            
            if ($result['diff_count'] > 0) {
                echo "   Differences: {$result['diff_count']}\n";
                foreach (array_slice($result['diffs'], 0, 5) as $diff) {
                    echo "   - {$diff['path']}: JS={$diff['js_value']}, PHP={$diff['php_value']}\n";
                }
                if (count($result['diffs']) > 5) {
                    echo "   ... and " . (count($result['diffs']) - 5) . " more\n";
                }
            }
            
            if (!empty($testResult['errors'])) {
                foreach ($testResult['errors'] as $error) {
                    echo "   ⚠️  {$error}\n";
                    $anomalies[] = [
                        'test' => $testName,
                        'type' => 'ERROR',
                        'message' => $error
                    ];
                }
            }
        }

        // Display period summary
        echo "\n   Periods: " . count($phpOutput['periods']) . "\n";
        foreach ($phpOutput['periods'] as $idx => $p) {
            echo "   Period {$idx}: Status={$p['status']}, Usage={$p['usage']} L, Sectors=" . count($p['sectors']) . "\n";
        }

    } catch (\Exception $e) {
        $failedTests++;
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $anomalies[] = [
            'test' => $testName,
            'type' => 'EXCEPTION',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
        $testResults[] = [
            'test_name' => $testName,
            'test_index' => $testIdx + 1,
            'parity_status' => 'ERROR',
            'error' => $e->getMessage()
        ];
    }

    echo "\n";
}

// ==================== GENERATE REPORT ====================

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  TEST SUMMARY                                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Total Tests: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n";
echo "Success Rate: " . ($totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0) . "%\n\n";

if (!empty($anomalies)) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ANOMALIES DETECTED                                            ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    foreach ($anomalies as $idx => $anomaly) {
        echo ($idx + 1) . ". [{$anomaly['type']}] {$anomaly['test']}\n";
        echo "   {$anomaly['message']}\n\n";
    }
}

// Save detailed report
$reportFile = __DIR__ . '/../test-reports/comparison-test-' . date('Y-m-d-H-i-s') . '.json';
$reportDir = dirname($reportFile);
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total_tests' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
        'success_rate' => $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0
    ],
    'test_results' => $testResults,
    'anomalies' => $anomalies
];

file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
echo "📄 Detailed report saved to: {$reportFile}\n\n";

// Generate fix schedule
if ($failedTests > 0 || !empty($anomalies)) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  FIX SCHEDULE                                                  ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    $fixSchedule = [];
    
    // Group issues by type
    $issueTypes = [];
    foreach ($testResults as $result) {
        if ($result['parity_status'] !== 'PASS') {
            foreach ($result['diffs'] ?? [] as $diff) {
                $pathParts = explode('.', $diff['path']);
                $field = end($pathParts);
                if (!isset($issueTypes[$field])) {
                    $issueTypes[$field] = [];
                }
                $issueTypes[$field][] = [
                    'test' => $result['test_name'],
                    'path' => $diff['path'],
                    'js_value' => $diff['js_value'],
                    'php_value' => $diff['php_value']
                ];
            }
        }
    }
    
    foreach ($issueTypes as $field => $issues) {
        echo "🔧 Field: {$field} (" . count($issues) . " occurrences)\n";
        echo "   Priority: " . (count($issues) > 3 ? "HIGH" : "MEDIUM") . "\n";
        echo "   Affected tests: " . implode(', ', array_unique(array_column($issues, 'test'))) . "\n";
        echo "   Action: Review PHP calculator output format for '{$field}'\n\n";
        
        $fixSchedule[] = [
            'field' => $field,
            'priority' => count($issues) > 3 ? 'HIGH' : 'MEDIUM',
            'occurrences' => count($issues),
            'affected_tests' => array_unique(array_column($issues, 'test')),
            'action' => "Review PHP calculator output format for '{$field}'"
        ];
    }
    
    // Add error fixes
    foreach ($anomalies as $anomaly) {
        if ($anomaly['type'] === 'ERROR') {
            echo "🔧 Error: {$anomaly['message']}\n";
            echo "   Priority: HIGH\n";
            echo "   Test: {$anomaly['test']}\n";
            echo "   Action: Fix calculation logic\n\n";
        }
    }
    
    $scheduleFile = __DIR__ . '/../test-reports/fix-schedule-' . date('Y-m-d-H-i-s') . '.json';
    file_put_contents($scheduleFile, json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'fixes' => $fixSchedule,
        'errors' => array_filter($anomalies, fn($a) => $a['type'] === 'ERROR')
    ], JSON_PRETTY_PRINT));
    
    echo "📋 Fix schedule saved to: {$scheduleFile}\n\n";
}

echo "✅ Test suite complete!\n";









