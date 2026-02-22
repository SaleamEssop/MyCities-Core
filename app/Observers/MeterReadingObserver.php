<?php

namespace App\Observers;

use App\Models\Bill;
use App\Models\MeterReadings;
use App\Services\BillingEngine;
use App\Services\BillingPeriodCalculator;
use Illuminate\Support\Facades\Log;

/**
 * MeterReadingObserver
 * 
 * ARCHITECTURE REFERENCE: See docs/ComprehensiveBillingArchitecture.md
 * 
 * ROLE: Integration layer - triggers billing when readings arrive
 * 
 * RESPONSIBILITIES:
 * - Detects new actual readings
 * - Calls BillingPeriodCalculator to get periods
 * - Calls BillingEngine.process() with precomputed periods
 * - Persists bills to database
 * 
 * INTERACTS WITH:
 * - Calls: BillingPeriodCalculator, BillingEngine
 * - Persists: Bill models
 * 
 * @see docs/ComprehensiveBillingArchitecture.md for complete architecture
 */
class MeterReadingObserver
{
    protected BillingEngine $billingEngine;
    protected BillingPeriodCalculator $periodCalculator;

    public function __construct(BillingEngine $billingEngine, BillingPeriodCalculator $periodCalculator)
    {
        $this->billingEngine = $billingEngine;
        $this->periodCalculator = $periodCalculator;
    }

    /**
     * Handle the MeterReadings "created" event.
     * When a new reading is submitted:
     * 1. Auto-generate bill if opening reading exists (for any reading type - 2 readings allow consumption calculation)
     * 2. Reconcile provisional periods if this is an actual reading
     * 3. Handle Projected → Actual transition if reading is on bill day
     */
    public function created(MeterReadings $reading): void
    {
        $meter = $reading->meter;
        if (!$meter) {
            return;
        }

        $account = $meter->account;
        if (!$account) {
            return;
        }

        try {
            // Step 1: Auto-generate bill if opening reading exists
            // Bills can be generated for any reading type when 2 readings exist (allows consumption calculation)
            $this->generateBillIfNeeded($account, $meter, $reading);
            
            // Step 2: Reconcile provisional periods only for ACTUAL readings
            // Reconciliation logic is specific to actual readings that trigger adjustments
            if ($reading->isActual()) {
                $reconciliationResult = $this->billingEngine->reconcileProvisionalPeriods($account, $reading);
                
                if ($reconciliationResult['success'] ?? false) {
                    Log::info('Billing reconciliation triggered', [
                        'meter_id' => $meter->id,
                        'reading_id' => $reading->id,
                        'daily_average' => $reconciliationResult['daily_average'] ?? 0,
                        'adjustments_count' => count($reconciliationResult['adjustments'] ?? []),
                    ]);
                }
            }
            
            // Step 3: Handle Projected → Actual transition if reading is on bill day
            // This transition logic only applies to actual readings
            if ($reading->isActual()) {
                $this->handleProjectedToActualTransition($account, $meter, $reading);
            }
            
        } catch (\Exception $e) {
            Log::error('Meter reading observer failed', [
                'meter_id' => $meter->id,
                'reading_id' => $reading->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Generate a bill if an opening reading exists for this meter.
     *
     * @param \App\Models\Account $account
     * @param \App\Models\Meter $meter
     * @param \App\Models\MeterReadings $closingReading
     * @return void
     */
    protected function generateBillIfNeeded($account, $meter, $closingReading): void
    {
        // Find the previous reading (opening reading) for this meter
        // Order by reading_date desc to get the most recent reading before this one
        $openingReading = MeterReadings::where('meter_id', $meter->id)
            ->where('id', '<', $closingReading->id)
            ->orderBy('reading_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$openingReading) {
            Log::info('No opening reading found for bill generation', [
                'meter_id' => $meter->id,
                'closing_reading_id' => $closingReading->id,
            ]);
            return;
        }

        // Check if a bill already exists for this reading pair
        $existingBill = Bill::where('meter_id', $meter->id)
            ->where('closing_reading_id', $closingReading->id)
            ->first();

        if ($existingBill) {
            Log::info('Bill already exists for this reading pair', [
                'bill_id' => $existingBill->id,
                'meter_id' => $meter->id,
                'closing_reading_id' => $closingReading->id,
            ]);
            return;
        }

        try {
            // Prepare readings array
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

            $tariff = $account->tariffTemplate;
            if (!$tariff) {
                Log::warning('Cannot generate bill - no tariff template', [
                    'account_id' => $account->id,
                    'meter_id' => $meter->id,
                ]);
                return;
            }

            // Check billing mode - DATE_TO_DATE or MONTHLY
            $isDateToDate = $tariff->isDateToDateBilling();

            if ($isDateToDate) {
                // ✅ DATE_TO_DATE MODE: Use DateToDatePeriodCalculator
                $this->generateDateToDateBill($account, $meter, $tariff, $closingReading);
            } else {
                // ✅ MONTHLY MODE: Use existing BillingPeriodCalculator logic
                $this->generateMonthlyBill($account, $meter, $tariff, $openingReading, $closingReading, $readings);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-generate bills', [
                'account_id' => $account->id,
                'meter_id' => $meter->id,
                'opening_reading_id' => $openingReading->id ?? null,
                'closing_reading_id' => $closingReading->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Generate bills for DATE_TO_DATE mode
     * Only stores CLOSED periods (OPEN periods are not stored)
     */
    protected function generateDateToDateBill($account, $meter, $tariff, $closingReading): void
    {
        // Use DateToDatePeriodCalculator to get all periods
        $dateToDateCalculator = app(\App\Services\DateToDatePeriodCalculator::class);
        $periods = $dateToDateCalculator->calculatePeriods($account, collect([$meter]));

        if (empty($periods)) {
            Log::info('No periods calculated for DATE_TO_DATE mode', [
                'account_id' => $account->id,
                'meter_id' => $meter->id,
            ]);
            return;
        }

        // Convert tariff to snapshot format
        $tariffSnapshot = $this->convertTariffToSnapshot($tariff);
        $fixedCharges = $this->getFixedCharges($tariff, $account);
        $editableCharges = $this->getEditableCharges($account);
        $isWater = $tariff->is_water ?? false;

        $billsCreated = [];

        // Only process CLOSED periods (skip OPEN periods - current active period)
        foreach ($periods as $period) {
            if ($period['status'] !== 'CLOSED') {
                Log::info('Skipping OPEN period for DATE_TO_DATE mode', [
                    'account_id' => $account->id,
                    'meter_id' => $meter->id,
                    'period_number' => $period['period_number'] ?? null,
                    'status' => $period['status'] ?? 'UNKNOWN',
                ]);
                continue; // ✅ Skip OPEN periods - they are not stored
            }

            // Check if bill already exists for this period
            $existingBill = Bill::where('meter_id', $meter->id)
                ->where('account_id', $account->id)
                ->where('billing_mode', 'DATE_TO_DATE')
                ->where('period_start_date', $period['start_date'])
                ->where('period_end_date', $period['end_date'])
                ->first();

            if ($existingBill) {
                Log::info('Bill already exists for DATE_TO_DATE period', [
                    'bill_id' => $existingBill->id,
                    'period_start' => $period['start_date'],
                    'period_end' => $period['end_date'],
                ]);
                continue;
            }

            // Prepare readings array for this period
            $readings = array_map(function($r) {
                return [
                    'date' => $r['date'],
                    'value' => (float) $r['value'],
                    'type' => 'ACTUAL' // DATE_TO_DATE uses actual readings
                ];
            }, $period['readings'] ?? []);

            if (count($readings) < 2) {
                Log::warning('Not enough readings in DATE_TO_DATE period', [
                    'account_id' => $account->id,
                    'meter_id' => $meter->id,
                    'period_number' => $period['period_number'] ?? null,
                    'readings_count' => count($readings),
                ]);
                continue;
            }

            // Convert period to BillingEngine format (for compatibility)
            $billingPeriods = [[
                'start' => $period['start_date'],
                'end' => $period['end_date'],
                'readings' => $readings
            ]];

            // Process with BillingEngine
            $result = $this->billingEngine->process(
                $readings,
                $billingPeriods,
                $tariffSnapshot,
                $fixedCharges,
                $editableCharges,
                [],
                $tariff,
                $isWater
            );

            if (!$result['can_bill'] || empty($result['bills'])) {
                Log::warning('BillingEngine returned no bills for DATE_TO_DATE period', [
                    'account_id' => $account->id,
                    'meter_id' => $meter->id,
                    'period_number' => $period['period_number'] ?? null,
                    'reason' => $result['reason'] ?? 'unknown',
                ]);
                continue;
            }

            // Get the bill data (should only be one period)
            $billData = $result['bills'][0] ?? null;
            if (!$billData) {
                continue;
            }

            // Find opening and closing readings for this period
            $openingReading = MeterReadings::whereIn('id', array_column($period['readings'] ?? [], 'reading_id'))
                ->orderBy('reading_date', 'asc')
                ->first();
            $periodClosingReading = MeterReadings::whereIn('id', array_column($period['readings'] ?? [], 'reading_id'))
                ->orderBy('reading_date', 'desc')
                ->first();

            // Prepare bill data with period information
            $billDataForCreation = array_merge($billData, [
                'period_start_date' => $period['start_date'],
                'period_end_date' => $period['end_date'],
                'sector_readings' => $period['readings'] ?? [],
                'opening_reading_id' => $openingReading?->id,
                'closing_reading_id' => $periodClosingReading?->id,
                'fixed_charges' => $fixedCharges,
                'editable_charges' => $editableCharges,
                'bill_breakdown' => $billData['bill_breakdown'] ?? null,
            ]);

            // Calculate VAT and total
            $vatRate = $tariff->getVatRate();
            if ($vatRate === null) {
                Log::error("MeterReadingObserver: Tariff #{$tariff->id} is missing VAT rate for DATE_TO_DATE mode.", [
                    'account_id' => $account->id,
                    'tariff_id' => $tariff->id,
                ]);
                continue;
            }

            if (isset($billData['bill_breakdown'])) {
                $vatAmount = round($billData['bill_breakdown']['vat']['amount'] ?? 0, 2);
                $billTotal = round($billData['bill_breakdown']['total'] ?? 0, 2);
            } else {
                $vatAmount = $vatRate > 0 
                    ? round($billData['total_of_all_charges'] * ($vatRate / 100), 2)
                    : 0;
                $billTotal = $billData['total_of_all_charges'] + $vatAmount;
            }

            $billDataForCreation['vat_amount'] = $vatAmount;
            $billDataForCreation['bill_total'] = $billTotal + ($billData['adjustment_brought_forward'] ?? 0);

            // Determine usage status (DATE_TO_DATE: PROVISIONAL, ACTUAL, or CALCULATED based on reading timing)
            // For now, default to PROVISIONAL (can be recalculated later)
            $billDataForCreation['usage_status'] = $billData['usage_status'] ?? \App\Services\BillingEngine::USAGE_PROVISIONAL;
            $billDataForCreation['bill_total_status'] = $billData['bill_total_status'] ?? \App\Services\BillingEngine::TOTAL_PROVISIONAL;

            // Create bill
            $bill = $this->billingEngine->createBill($billDataForCreation, $account, $meter);
            $billsCreated[] = $bill->id;

            Log::info('DATE_TO_DATE bill created successfully', [
                'bill_id' => $bill->id,
                'account_id' => $account->id,
                'meter_id' => $meter->id,
                'period_start' => $period['start_date'],
                'period_end' => $period['end_date'],
                'status' => $period['status'],
            ]);
        }

        Log::info('DATE_TO_DATE bills auto-generated successfully', [
            'bills_created' => count($billsCreated),
            'bill_ids' => $billsCreated,
            'account_id' => $account->id,
            'meter_id' => $meter->id,
        ]);
    }

    /**
     * Generate bills for MONTHLY mode
     * Only stores PROVISIONAL, ACTUAL, or CALCULATED periods (skips PROJECTED - current open period)
     */
    protected function generateMonthlyBill($account, $meter, $tariff, $openingReading, $closingReading, array $readings): void
    {
        // Get bill day - REQUIRED: no fallbacks allowed
        $accountBillDay = $account->bill_day;
        $tariffBillDay = $tariff->billing_day;
        if (is_null($accountBillDay) && (is_null($tariffBillDay) || $tariffBillDay === '')) {
            \Log::error("MeterReadingObserver: Account #{$account->id} is missing bill_day for MONTHLY mode. Cannot generate bill.", [
                'account_id' => $account->id,
                'reading_id' => $closingReading->id,
            ]);
            return; // Skip bill generation
        }
        $billDay = !is_null($accountBillDay) && $accountBillDay > 0 ? $accountBillDay : $tariffBillDay;

        // Calculate periods using BillingPeriodCalculator
        $periods = $this->periodCalculator->calculatePeriods(
            $billDay,
            $readings[0]['date'],
            $readings[1]['date']
        );

        if (empty($periods)) {
            Log::warning('No periods calculated for MONTHLY mode', [
                'account_id' => $account->id,
                'meter_id' => $meter->id,
                'start_date' => $readings[0]['date'],
                'end_date' => $readings[1]['date'],
            ]);
            return;
        }

        // Convert tariff to snapshot format
        $tariffSnapshot = $this->convertTariffToSnapshot($tariff);
        $fixedCharges = $this->getFixedCharges($tariff, $account);
        $editableCharges = $this->getEditableCharges($account);
        $isWater = $tariff->is_water ?? false;

        // Process readings with precomputed periods
        $result = $this->billingEngine->process(
            $readings,
            $periods,
            $tariffSnapshot,
            $fixedCharges,
            $editableCharges,
            [],
            $tariff,
            $isWater
        );

        if (!$result['can_bill'] || empty($result['bills'])) {
            Log::warning('BillingEngine returned no bills for MONTHLY mode', [
                'account_id' => $account->id,
                'meter_id' => $meter->id,
                'reason' => $result['reason'] ?? 'unknown',
            ]);
            return;
        }

        // Create bills for each period - but skip PROJECTED periods (current open period)
        $billsCreated = [];
        foreach ($result['bills'] as $billData) {
            $usageStatus = $billData['usage_status'] ?? \App\Services\BillingEngine::USAGE_PROVISIONAL;
            
            // ✅ Skip PROJECTED periods - they are not stored (current open period exception)
            if ($usageStatus === \App\Services\BillingEngine::USAGE_PROJECTED) {
                Log::info('Skipping PROJECTED period for MONTHLY mode (current open period)', [
                    'account_id' => $account->id,
                    'meter_id' => $meter->id,
                    'period_start' => $billData['start'] ?? null,
                    'period_end' => $billData['end'] ?? null,
                    'usage_status' => $usageStatus,
                ]);
                continue; // ✅ Skip PROJECTED periods - they are not stored
            }

            // Only store PROVISIONAL, ACTUAL, or CALCULATED periods
            if (!in_array($usageStatus, [
                \App\Services\BillingEngine::USAGE_PROVISIONAL,
                \App\Services\BillingEngine::USAGE_ACTUAL,
                \App\Services\BillingEngine::USAGE_CALCULATED
            ])) {
                Log::warning('Unknown usage status for MONTHLY bill', [
                    'account_id' => $account->id,
                    'meter_id' => $meter->id,
                    'usage_status' => $usageStatus,
                ]);
                continue;
            }

            // Check if bill already exists for this period
            $existingBill = Bill::where('meter_id', $meter->id)
                ->where('account_id', $account->id)
                ->where('billing_mode', 'MONTHLY')
                ->where('period_start_date', $billData['start'] ?? null)
                ->where('period_end_date', $billData['end'] ?? null)
                ->first();

            if ($existingBill) {
                Log::info('Bill already exists for MONTHLY period', [
                    'bill_id' => $existingBill->id,
                    'period_start' => $billData['start'] ?? null,
                    'period_end' => $billData['end'] ?? null,
                ]);
                continue;
            }

            // Prepare bill data for creation with period dates
            $billDataForCreation = array_merge($billData, [
                'period_start_date' => $billData['start'] ?? null, // ✅ ADD: Period start date
                'period_end_date' => $billData['end'] ?? null, // ✅ ADD: Period end date
                'opening_reading_id' => $openingReading->id,
                'closing_reading_id' => $closingReading->id,
                'fixed_charges' => $fixedCharges,
                'editable_charges' => $editableCharges,
                'bill_breakdown' => $billData['bill_breakdown'] ?? null,
            ]);

            // Calculate VAT and total
            if (isset($billData['bill_breakdown'])) {
                $vatAmount = round($billData['bill_breakdown']['vat']['amount'] ?? 0, 2);
                $billTotal = round($billData['bill_breakdown']['total'] ?? 0, 2);
            } else {
                $vatRate = $tariff->getVatRate();
                if ($vatRate === null) {
                    \Log::error("MeterReadingObserver: Tariff #{$tariff->id} is missing VAT rate for MONTHLY mode.", [
                        'account_id' => $account->id,
                        'tariff_id' => $tariff->id,
                        'reading_id' => $closingReading->id,
                    ]);
                    continue;
                }
                $vatAmount = $vatRate > 0 
                    ? round($billData['total_of_all_charges'] * ($vatRate / 100), 2)
                    : 0;
                $billTotal = $billData['total_of_all_charges'] + $vatAmount;
            }
            
            $billDataForCreation['vat_amount'] = $vatAmount;
            $billDataForCreation['bill_total'] = $billTotal + ($billData['adjustment_brought_forward'] ?? 0);

            // Create bill
            $bill = $this->billingEngine->createBill($billDataForCreation, $account, $meter);
            $billsCreated[] = $bill->id;
        }

        Log::info('MONTHLY bills auto-generated successfully', [
            'bills_created' => count($billsCreated),
            'bill_ids' => $billsCreated,
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'opening_reading_id' => $openingReading->id,
            'closing_reading_id' => $closingReading->id,
            'periods_processed' => count($result['bills']),
        ]);
    }

    /**
     * Convert tariff template to snapshot format
     * 
     * @param \App\Models\RegionsAccountTypeCost $tariff
     * @return array Tariff snapshot
     */
    private function convertTariffToSnapshot($tariff): array
    {
        $tiers = $tariff->tiers()->orderBy('tier_number')->get();
        
        if ($tiers->isEmpty()) {
            // Fall back to legacy structure
            $legacyTiers = [];
            if ($tariff->is_water && !empty($tariff->water_in)) {
                foreach ($tariff->water_in as $tier) {
                    $legacyTiers[] = [
                        'limit' => (float) ($tier['max'] ?? PHP_FLOAT_MAX),
                        'rate' => (float) ($tier['cost'] ?? 0)
                    ];
                }
            } elseif ($tariff->is_electricity && !empty($tariff->electricity)) {
                foreach ($tariff->electricity as $tier) {
                    $legacyTiers[] = [
                        'limit' => (float) ($tier['max'] ?? PHP_FLOAT_MAX),
                        'rate' => (float) ($tier['cost'] ?? 0)
                    ];
                }
            }
            return ['tiers' => $legacyTiers];
        }

        // Convert database tiers to snapshot format
        $tiersSnapshot = [];
        foreach ($tiers as $tier) {
            $tiersSnapshot[] = [
                'limit' => $tier->max_units !== null ? (float) $tier->max_units : PHP_FLOAT_MAX,
                'rate' => (float) $tier->rate_per_unit
            ];
        }

        return ['tiers' => $tiersSnapshot];
    }

    /**
     * Get fixed charges for account
     * 
     * @param \App\Models\RegionsAccountTypeCost $tariff
     * @param \App\Models\Account $account
     * @return array Fixed charges
     */
    private function getFixedCharges($tariff, $account): array
    {
        $charges = [];

        // Get tariff fixed costs
        $tariffFixedCosts = $tariff->tariffFixedCosts;
        foreach ($tariffFixedCosts as $cost) {
            $charges[] = [
                'name' => $cost->name,
                'amount' => (float) $cost->amount
            ];
        }

        // Fall back to legacy fixed_costs array if no tariff fixed costs
        if (empty($charges) && !empty($tariff->fixed_costs)) {
            foreach ($tariff->fixed_costs as $cost) {
                $charges[] = [
                    'name' => $cost['name'] ?? 'Fixed Cost',
                    'amount' => (float) ($cost['value'] ?? $cost['amount'] ?? 0)
                ];
            }
        }

        return $charges;
    }

    /**
     * Get editable charges for account
     * 
     * @param \App\Models\Account $account
     * @return array Editable charges
     */
    private function getEditableCharges($account): array
    {
        $charges = [];

        // Get account fixed costs
        $account->loadMissing('defaultFixedCosts.fixedCost');
        foreach ($account->defaultFixedCosts as $accountFixedCost) {
            if (!$accountFixedCost->is_active) continue;
            
            $amount = (float) ($accountFixedCost->value ?? 0);
            if ($amount > 0) {
                $charges[] = [
                    'name' => $accountFixedCost->fixedCost?->title ?? 'Account Fixed Cost',
                    'amount' => $amount
                ];
            }
        }

        // Get customer_costs
        $customerCosts = $account->customer_costs ?? [];
        foreach ($customerCosts as $cost) {
            $amount = (float) ($cost['value'] ?? $cost['amount'] ?? 0);
            if ($amount > 0) {
                $charges[] = [
                    'name' => $cost['name'] ?? 'Customer Cost',
                    'amount' => $amount
                ];
            }
        }

        // Get fixed_costs from account
        $fixedCosts = $account->fixed_costs ?? [];
        foreach ($fixedCosts as $cost) {
            $amount = (float) ($cost['value'] ?? $cost['amount'] ?? 0);
            if ($amount > 0) {
                $charges[] = [
                    'name' => $cost['name'] ?? 'Fixed Cost',
                    'amount' => $amount
                ];
            }
        }

        return $charges;
    }

    /**
     * Handle Projected → Actual transition when user submits reading on bill day
     * 
     * This confirms the end of the current period (confirmation, not rewriting history)
     * 
     * @param \App\Models\Account $account
     * @param \App\Models\Meter $meter
     * @param \App\Models\MeterReadings $reading
     * @return void
     */
    protected function handleProjectedToActualTransition($account, $meter, $reading): void
    {
        $tariff = $account->tariffTemplate;
        if (!$tariff) {
            return;
        }

        // Get bill day
        $accountBillDay = $account->bill_day;
        $tariffBillDay = $tariff->billing_day;
        if (is_null($accountBillDay) && (is_null($tariffBillDay) || $tariffBillDay === '')) {
            return;
        }
        $billDay = !is_null($accountBillDay) && $accountBillDay > 0 ? $accountBillDay : $tariffBillDay;

        // Check if reading is on bill day (or grace window)
        $readingDate = \Carbon\Carbon::parse($reading->reading_date);
        $periodCalculator = new BillingPeriodCalculator();
        $readingPeriod = $periodCalculator->findPeriodForDate($readingDate->format('Y-m-d'), $billDay);
        $periodEnd = \Carbon\Carbon::parse($readingPeriod['end']); // bill_day of next period
        $billDayDate = $periodEnd->copy();
        $oneDayBefore = $billDayDate->copy()->subDay();
        $oneDayAfter = $billDayDate->copy()->addDay();

        $isOnBillDay = $readingDate->isSameDay($billDayDate) ||
                      $readingDate->isSameDay($oneDayBefore) ||
                      ($readingDate->isSameDay($oneDayAfter));

        if (!$isOnBillDay) {
            return; // Not on bill day, no transition
        }

        // Find current period bill with PROJECTED status
        $currentPeriodBill = Bill::where('account_id', $account->id)
            ->where('meter_id', $meter->id)
            ->where('usage_status', \App\Services\BillingEngine::USAGE_PROJECTED)
            ->whereHas('openingReading', function($q) use ($readingPeriod) {
                $q->whereDate('reading_date', '<=', $readingPeriod['start']);
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($currentPeriodBill) {
            // Transition: Projected → Actual
            $currentPeriodBill->usage_status = \App\Services\BillingEngine::USAGE_ACTUAL;
            $currentPeriodBill->bill_total_status = \App\Services\BillingEngine::TOTAL_ACTUAL;
            $currentPeriodBill->finalized_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            $currentPeriodBill->save();

            Log::info('Period transition: PROJECTED → ACTUAL', [
                'bill_id' => $currentPeriodBill->id,
                'meter_id' => $meter->id,
                'reading_id' => $reading->id,
                'reading_date' => $readingDate->format('Y-m-d'),
            ]);
        }
    }
}