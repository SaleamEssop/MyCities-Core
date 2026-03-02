<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Bill;
use App\Models\MeterReadings;
use App\Services\Billing\Calculator;
use App\Services\Billing\Calendar;
use App\Services\BillingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Regenerate all bills for an account using TariffCalculatorService.
 * 
 * This command deletes existing bills and regenerates them with the new
 * calculation logic that includes full breakdown from TariffCalculatorService.
 * 
 * Usage:
 *   php artisan bills:regenerate-with-breakdown {accountId}
 *   php artisan bills:regenerate-with-breakdown 2
 */
class RegenerateBillsWithBreakdown extends Command
{
    protected $signature = 'bills:regenerate-with-breakdown 
                            {accountId : The account ID to regenerate bills for}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Regenerate all bills for an account using TariffCalculatorService with full breakdown';

    public function handle()
    {
        $accountId = $this->argument('accountId');
        $dryRun = $this->option('dry-run');

        $account = Account::find($accountId);
        if (!$account) {
            $this->error("Account {$accountId} not found");
            return 1;
        }

        $tariff = $account->tariffTemplate;
        if (!$tariff) {
            $this->error("Account {$accountId} has no tariff template assigned");
            return 1;
        }

        $this->info("Regenerating bills for Account: {$account->account_name} (ID: {$accountId})");
        $this->info("Tariff: {$tariff->template_name}");
        $this->info("Is Water: " . ($tariff->is_water ? 'Yes' : 'No'));
        $this->info("Is Electricity: " . ($tariff->is_electricity ? 'Yes' : 'No'));

        // Get all meters for this account
        $meters = $account->meters;
        $this->info("Found {$meters->count()} meter(s)");

        if ($dryRun) {
            $this->warn("DRY RUN - No changes will be made");
        }

        $billingEngine  = app(BillingEngine::class);
        $calendar       = new Calendar();
        $billingCalc    = new Calculator($calendar);

        $totalBillsDeleted = 0;
        $totalBillsCreated = 0;

        foreach ($meters as $meter) {
            $this->info("\nProcessing Meter: {$meter->meter_number} (ID: {$meter->id})");
            
            // Get meter type
            $meterType = $meter->meterTypes;
            $isWater = $meterType && strtolower(trim($meterType->title)) === 'water';
            $this->info("  Meter Type: " . ($meterType ? $meterType->title : 'Unknown') . " (isWater: " . ($isWater ? 'Yes' : 'No') . ")");

            // Get existing bills count
            $existingBills = Bill::where('meter_id', $meter->id)
                ->where('account_id', $account->id)
                ->count();
            $this->info("  Existing bills: {$existingBills}");

            // Get all readings for this meter
            $allReadings = MeterReadings::where('meter_id', $meter->id)
                ->where('reading_type', 'ACTUAL')
                ->orderBy('reading_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $this->info("  Readings count: {$allReadings->count()}");

            if ($allReadings->count() < 2) {
                $this->warn("  Skipping - need at least 2 readings to generate bills");
                continue;
            }

            // Show reading summary
            $this->table(
                ['Date', 'Value', 'Type'],
                $allReadings->map(fn($r) => [
                    $r->reading_date->format('Y-m-d'),
                    number_format($r->reading_value, 2),
                    $r->reading_type
                ])->toArray()
            );

            if (!$dryRun) {
                // Delete existing bills for this meter
                $deleted = Bill::where('meter_id', $meter->id)
                    ->where('account_id', $account->id)
                    ->delete();
                $totalBillsDeleted += $deleted;
                $this->info("  Deleted {$deleted} existing bill(s)");

                // Regenerate bills for each pair of consecutive readings
                // REQUIRED: no fallbacks allowed
                // Note: Use is_null() instead of empty() because empty(0) returns true
                $accountBillDay = $account->bill_day;
                $tariffBillDay = $tariff->billing_day;
                if (is_null($accountBillDay) && (is_null($tariffBillDay) || $tariffBillDay === '')) {
                    $this->error("Account #{$account->id} is missing bill_day. Skipping.");
                    continue;
                }
                $billDay = !is_null($accountBillDay) && $accountBillDay > 0 ? $accountBillDay : $tariffBillDay;
                $this->info("  Bill Day: {$billDay}");

                for ($i = 1; $i < $allReadings->count(); $i++) {
                    $openingReading = $allReadings[$i - 1];
                    $closingReading = $allReadings[$i];

                    $this->info("  Processing: {$openingReading->reading_date->format('Y-m-d')} -> {$closingReading->reading_date->format('Y-m-d')}");

                    try {
                        $readings = [
                            [
                                'date' => $openingReading->reading_date->format('Y-m-d'),
                                'value' => (float) $openingReading->reading_value,
                                'type' => $openingReading->reading_type ?? 'ACTUAL'
                            ],
                            [
                                'date' => $closingReading->reading_date->format('Y-m-d'),
                                'value' => (float) $closingReading->reading_value,
                                'type' => $closingReading->reading_type ?? 'ACTUAL'
                            ]
                        ];

                        $periods = $billingCalc->calculatePeriods(
                            $billDay,
                            $readings[0]['date'],
                            $readings[1]['date']
                        );

                        if (empty($periods)) {
                            $this->warn("    No periods calculated - skipping");
                            continue;
                        }

                        $tariffSnapshot = $billingEngine->convertTariffToSnapshot($tariff);
                        $fixedCharges = $billingEngine->getFixedCharges($tariff, $account);
                        $editableCharges = $billingEngine->getEditableCharges($account);

                        // Pass tariff model and meter type for TariffCalculatorService
                        $result = $billingEngine->process(
                            $readings,
                            $periods,
                            $tariffSnapshot,
                            $fixedCharges,
                            $editableCharges,
                            [],
                            $tariff, // Pass full tariff model
                            $isWater // Pass meter type
                        );

                        if ($result['can_bill'] && !empty($result['bills'])) {
                            foreach ($result['bills'] as $billData) {
                                // Add reading objects to bill data
                                $billData['openingReading'] = $openingReading;
                                $billData['closingReading'] = $closingReading;

                                // Calculate VAT from breakdown if available
                                if (isset($billData['bill_breakdown']['vat']['amount'])) {
                                    $billData['vat_amount'] = $billData['bill_breakdown']['vat']['amount'];
                                    $billData['bill_total'] = $billData['bill_breakdown']['total'] ?? $billData['total_of_all_charges'];
                                } else {
                                    // Get VAT rate - REQUIRED: no fallbacks allowed
                                    $vatRate = $tariff->getVatRate();
                                    if ($vatRate === null) {
                                        $this->error("Tariff #{$tariff->id} is missing VAT rate. Skipping.");
                                        continue;
                                    }
                                    $billData['vat_amount'] = $vatRate > 0 
                                        ? round($billData['total_of_all_charges'] * ($vatRate / 100), 2)
                                        : 0;
                                    $billData['bill_total'] = $billData['total_of_all_charges'] + $billData['vat_amount'];
                                }

                                $bill = $billingEngine->createBill($billData, $account, $meter);
                                $totalBillsCreated++;

                                $this->info("    Created bill ID: {$bill->id}");
                                $this->info("      Consumption: " . number_format($bill->consumption, 2) . ($isWater ? ' L' : ' kWh'));
                                $this->info("      Usage Charge: R" . number_format($bill->usage_charge ?? $bill->tiered_charge, 2));
                                $this->info("      Fixed Costs: R" . number_format($bill->fixed_costs_total, 2));
                                $this->info("      VAT: R" . number_format($bill->vat_amount, 2));
                                $this->info("      Total: R" . number_format($bill->total_amount, 2));
                                
                                // Check if breakdown was stored
                                $hasBreakdown = !empty($bill->tier_breakdown) && 
                                    (isset($bill->tier_breakdown['water']) || isset($bill->tier_breakdown['electricity']));
                                $this->info("      Has TariffCalculator Breakdown: " . ($hasBreakdown ? 'Yes' : 'No'));
                            }
                        } else {
                            $this->warn("    BillingEngine returned no bills: " . ($result['reason'] ?? 'unknown'));
                        }
                    } catch (\Exception $e) {
                        $this->error("    Error: " . $e->getMessage());
                        Log::error('Bill regeneration error', [
                            'meter_id' => $meter->id,
                            'opening_reading_id' => $openingReading->id,
                            'closing_reading_id' => $closingReading->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Bills deleted: {$totalBillsDeleted}");
        $this->info("Bills created: {$totalBillsCreated}");

        if ($dryRun) {
            $this->warn("This was a DRY RUN - no changes were made");
            $this->info("Run without --dry-run to apply changes");
        }

        return 0;
    }
}

