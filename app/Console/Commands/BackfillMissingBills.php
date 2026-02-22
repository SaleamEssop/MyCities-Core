<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Services\BillingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillMissingBills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:backfill-missing-bills {accountId?} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing bills for accounts with readings but no bills';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $accountId = $this->argument('accountId');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No bills will be created");
        }
        
        if ($accountId) {
            $accounts = Account::where('id', $accountId)->with(['meters.readings', 'tariffTemplate'])->get();
        } else {
            $accounts = Account::with(['meters.readings', 'tariffTemplate'])->get();
        }
        
        $totalCreated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        
        foreach ($accounts as $account) {
            $this->info("=== Account ID: {$account->id} - {$account->account_name} ===");
            
            if (!$account->tariffTemplate) {
                $this->warn("  ⚠️  No tariff template - skipping");
                continue;
            }
            
            foreach ($account->meters as $meter) {
                $this->line("  Meter ID: {$meter->id} - {$meter->meter_title}");
                
                $readings = $meter->readings()
                    ->orderBy('reading_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
                
                if ($readings->count() < 2) {
                    $this->line("    Only {$readings->count()} reading(s) - need at least 2 to create bills");
                    continue;
                }
                
                // Process each consecutive pair
                for ($i = 1; $i < $readings->count(); $i++) {
                    $opening = $readings->get($i - 1);
                    $closing = $readings->get($i);
                    
                    // Check if bill already exists
                    $existingBill = Bill::where('meter_id', $meter->id)
                        ->where('opening_reading_id', $opening->id)
                        ->where('closing_reading_id', $closing->id)
                        ->first();
                    
                    if ($existingBill) {
                        $this->line("    ✓ Bill exists for readings {$opening->id} → {$closing->id}");
                        $totalSkipped++;
                        continue;
                    }
                    
                    // Only create bills for ACTUAL readings
                    if (!$opening->isActual() || !$closing->isActual()) {
                        $this->warn("    ⚠️  Skipping non-ACTUAL readings: {$opening->reading_type} → {$closing->reading_type}");
                        $totalSkipped++;
                        continue;
                    }
                    
                    try {
                        $billingEngine = app(BillingEngine::class);
                        $billResult = $billingEngine->calculateCharge($account, $opening, $closing);
                        
                        if ($dryRun) {
                            $this->info("    [DRY RUN] Would create bill:");
                            $this->line("      Consumption: {$billResult->consumption}");
                            $this->line("      Total Amount: {$billResult->totalAmount}");
                            $this->line("      Fixed Costs: {$billResult->fixedCostsTotal}");
                            $totalCreated++;
                        } else {
                            // Create bill even if amount is 0 (per business rule: if reading exists, bill must exist)
                            $bill = $billingEngine->createBill($billResult, $account, $meter);
                            
                            $this->info("    ✓ Created bill {$bill->id} for readings {$opening->id} → {$closing->id}");
                            $this->line("      Consumption: {$billResult->consumption}, Total: {$billResult->totalAmount}");
                            $totalCreated++;
                            
                            Log::info('Bill backfilled', [
                                'bill_id' => $bill->id,
                                'account_id' => $account->id,
                                'meter_id' => $meter->id,
                                'opening_reading_id' => $opening->id,
                                'closing_reading_id' => $closing->id,
                                'total_amount' => $bill->total_amount,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $this->error("    ❌ Error creating bill: {$e->getMessage()}");
                        $totalErrors++;
                        
                        Log::error('Failed to backfill bill', [
                            'account_id' => $account->id,
                            'meter_id' => $meter->id,
                            'opening_reading_id' => $opening->id,
                            'closing_reading_id' => $closing->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }
        }
        
        $this->line("");
        $this->info("=== Summary ===");
        $this->line("Created: {$totalCreated}");
        $this->line("Skipped: {$totalSkipped}");
        $this->line("Errors: {$totalErrors}");
        
        return 0;
    }
}





















