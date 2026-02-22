<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\MeterReadings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiagnoseMissingBills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:diagnose-missing-bills {accountId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose why bills are missing for accounts with readings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $accountId = $this->argument('accountId');
        
        if ($accountId) {
            $accounts = Account::where('id', $accountId)->get();
        } else {
            $accounts = Account::with(['meters.readings'])->get();
        }
        
        foreach ($accounts as $account) {
            $this->info("=== Account ID: {$account->id} - {$account->account_name} ===");
            
            foreach ($account->meters as $meter) {
                $this->line("  Meter ID: {$meter->id} - {$meter->meter_title}");
                
                $readings = $meter->readings()->orderBy('reading_date', 'asc')->get();
                $this->line("    Total Readings: {$readings->count()}");
                
                if ($readings->count() === 0) {
                    $this->warn("    ⚠️  No readings - skipping");
                    continue;
                }
                
                // Check reading types
                $actualReadings = $readings->filter(fn($r) => $r->isActual());
                $this->line("    Actual Readings: {$actualReadings->count()}");
                
                // Check for bills
                $bills = Bill::where('meter_id', $meter->id)->get();
                $this->line("    Existing Bills: {$bills->count()}");
                
                if ($bills->count() === 0 && $readings->count() > 0) {
                    $this->error("    ❌ MISSING BILLS - Readings exist but no bills!");
                    
                    // Analyze why bills weren't created
                    if ($readings->count() === 1) {
                        $this->warn("      → First reading only (no opening reading to create bill)");
                    } else {
                        // Check each consecutive pair
                        for ($i = 1; $i < $readings->count(); $i++) {
                            $opening = $readings->get($i - 1);
                            $closing = $readings->get($i);
                            
                            $this->line("      Checking pair: Reading {$opening->id} → {$closing->id}");
                            
                            if (!$opening->isActual() || !$closing->isActual()) {
                                $this->warn("        → One or both readings are not ACTUAL type");
                                $this->line("          Opening: {$opening->reading_type}");
                                $this->line("          Closing: {$closing->reading_type}");
                            }
                            
                            // Check if bill exists for this pair
                            $existingBill = Bill::where('meter_id', $meter->id)
                                ->where('opening_reading_id', $opening->id)
                                ->where('closing_reading_id', $closing->id)
                                ->first();
                            
                            if (!$existingBill) {
                                $this->error("        ❌ No bill for this pair");
                                
                                // Try to calculate what the bill would be
                                try {
                                    $billingEngine = app(\App\Services\BillingEngine::class);
                                    $result = $billingEngine->calculateCharge($account, $opening, $closing);
                                    
                                    $this->line("        Calculation result:");
                                    $this->line("          Consumption: {$result->consumption}");
                                    $this->line("          Total Amount: {$result->totalAmount}");
                                    $this->line("          Fixed Costs: {$result->fixedCostsTotal}");
                                    
                                    if ($result->totalAmount == 0 && $result->fixedCostsTotal == 0) {
                                        $this->warn("        ⚠️  Calculation resulted in 0 - bill would not be created");
                                    }
                                } catch (\Exception $e) {
                                    $this->error("        ❌ Error calculating: {$e->getMessage()}");
                                }
                            } else {
                                $this->info("        ✓ Bill exists: {$existingBill->id}");
                            }
                        }
                    }
                } else {
                    $this->info("    ✓ Bills exist");
                }
                
                $this->line("");
            }
        }
        
        return 0;
    }
}





















