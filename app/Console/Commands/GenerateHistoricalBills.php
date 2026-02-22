<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Services\BillingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateHistoricalBills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:generate-historical 
                            {--account= : Generate bills for a specific account ID only}
                            {--dry-run : Show what would be generated without creating bills}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate historical bills for all accounts with meter readings';

    protected BillingEngine $billingEngine;

    /**
     * Create a new command instance.
     */
    public function __construct(BillingEngine $billingEngine)
    {
        parent::__construct();
        $this->billingEngine = $billingEngine;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accountId = $this->option('account');
        $dryRun = $this->option('dry-run');

        $this->info('Generating historical bills...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No bills will be created');
        }

        // Get accounts to process
        $query = Account::with(['meters.readings', 'tariffTemplate']);
        
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();
        $this->info("Found {$accounts->count()} account(s) to process");

        $totalBillsCreated = 0;
        $totalBillsSkipped = 0;
        $totalErrors = 0;

        foreach ($accounts as $account) {
            $this->line("Processing Account: {$account->account_name} (ID: {$account->id})");

            if (!$account->tariffTemplate) {
                $this->warn("  ⚠ Skipping - No tariff template assigned");
                $totalBillsSkipped++;
                continue;
            }

            foreach ($account->meters as $meter) {
                $readings = $meter->readings()
                    ->orderBy('reading_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                if ($readings->count() < 2) {
                    $this->line("  ⚠ Meter {$meter->meter_number}: Insufficient readings ({$readings->count()})");
                    continue;
                }

                $this->line("  Processing Meter: {$meter->meter_number} ({$readings->count()} readings)");

                // Generate bills for each consecutive reading pair
                for ($i = 0; $i < $readings->count() - 1; $i++) {
                    $openingReading = $readings[$i];
                    $closingReading = $readings[$i + 1];

                    // Check if bill already exists for this reading pair
                    $existingBill = Bill::where('meter_id', $meter->id)
                        ->where('opening_reading_id', $openingReading->id)
                        ->where('closing_reading_id', $closingReading->id)
                        ->first();

                    if ($existingBill) {
                        $this->line("    ✓ Bill already exists (ID: {$existingBill->id})");
                        $totalBillsSkipped++;
                        continue;
                    }

                    try {
                        // Calculate bill
                        $billResult = $this->billingEngine->calculateCharge(
                            $account,
                            $openingReading,
                            $closingReading
                        );

                        // Only create bill if it has a positive amount
                        if ($billResult->totalAmount > 0 || $billResult->fixedCostsTotal > 0) {
                            if ($dryRun) {
                                $this->line("    [DRY RUN] Would create bill:");
                                $this->line("      Opening: {$openingReading->reading_date} ({$openingReading->reading_value})");
                                $this->line("      Closing: {$closingReading->reading_date} ({$closingReading->reading_value})");
                                $this->line("      Total: R" . number_format($billResult->totalAmount, 2));
                                $totalBillsCreated++;
                            } else {
                                $bill = $this->billingEngine->createBill(
                                    $billResult,
                                    $account,
                                    $meter
                                );

                                $this->line("    ✓ Created bill ID: {$bill->id} - R" . number_format($bill->total_amount, 2));
                                $totalBillsCreated++;
                            }
                        } else {
                            $this->line("    ⚠ Skipping - Zero amount bill");
                            $totalBillsSkipped++;
                        }
                    } catch (\Exception $e) {
                        $this->error("    ✗ Error: " . $e->getMessage());
                        Log::error('GenerateHistoricalBills error', [
                            'account_id' => $account->id,
                            'meter_id' => $meter->id,
                            'opening_reading_id' => $openingReading->id,
                            'closing_reading_id' => $closingReading->id,
                            'error' => $e->getMessage(),
                        ]);
                        $totalErrors++;
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Bills created: {$totalBillsCreated}");
        $this->line("  Bills skipped: {$totalBillsSkipped}");
        $this->line("  Errors: {$totalErrors}");

        return Command::SUCCESS;
    }
}
