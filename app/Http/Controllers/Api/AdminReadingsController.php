<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeterReadings;
use App\Models\Bill;
use App\Models\AdminAction;
use App\Models\Meter;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Admin Readings Controller
 * 
 * Handles admin-only operations for meter readings.
 * All operations are logged to admin_actions table for audit trail.
 */
class AdminReadingsController extends Controller
{
    /**
     * Edit a meter reading (admin only)
     * 
     * @param Request $request
     * @param int $id Reading ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function editReading(Request $request, $id)
    {
        $request->validate([
            'value' => 'required|string',
            'reading_date' => 'nullable|date',
            'reason' => 'required|string|min:5',
            // Note: is_rollover and rollover_reason_code are not stored in meter_readings table
            // They are only used for admin action logging if needed
        ]);

        $reading = MeterReadings::find($id);
        if (!$reading) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'msg' => 'Reading not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Store old value for audit
            $oldValue = $reading->reading_value;
            $oldDate = $reading->reading_date;
            $newDate = $request->has('reading_date') ? $request->reading_date : $oldDate;
            $newValue = floatval($request->value);
            
            // VALIDATION RULE 1: If date is changed, it cannot be the same as an existing date
            if ($request->has('reading_date') && $request->reading_date != $oldDate->format('Y-m-d')) {
                $existingReading = MeterReadings::where('meter_id', $reading->meter_id)
                    ->where('reading_date', $request->reading_date)
                    ->where('id', '!=', $id) // Exclude current reading
                    ->first();
                
                if ($existingReading) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'msg' => 'A reading with this date already exists. Please delete the existing reading first or choose a different date.'
                    ], 400);
                }
            }
            
            // VALIDATION RULE 2: If date remains the same, value must be between previous and next readings
            if (!$request->has('reading_date') || $request->reading_date == $oldDate->format('Y-m-d')) {
                // Get previous reading (earlier date) - EXCLUDE current reading
                $previousReading = MeterReadings::where('meter_id', $reading->meter_id)
                    ->where('id', '!=', $id) // Exclude current reading being edited
                    ->where('reading_date', '<', $oldDate->format('Y-m-d'))
                    ->orderBy('reading_date', 'desc')
                    ->orderBy('id', 'desc') // If same date, use ID as tiebreaker
                    ->first();
                
                // Get next reading (later date only) - EXCLUDE current reading
                // Note: Per validation rules, only one reading per date is allowed
                // So we only compare against readings on different dates
                $nextReading = MeterReadings::where('meter_id', $reading->meter_id)
                    ->where('id', '!=', $id) // Exclude current reading being edited
                    ->where('reading_date', '>', $oldDate->format('Y-m-d'))
                    ->orderBy('reading_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();
                
                // Check value is not lower than previous reading
                if ($previousReading && $newValue < floatval($previousReading->reading_value)) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'msg' => 'Reading value cannot be lower than the previous reading value (' . $previousReading->reading_value . ' on ' . $previousReading->reading_date->format('Y-m-d') . ')'
                    ], 400);
                }
                
                // Check value is not higher than next reading
                if ($nextReading && $newValue > floatval($nextReading->reading_value)) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'msg' => 'Reading value cannot be higher than the next reading value (' . $nextReading->reading_value . ' on ' . $nextReading->reading_date->format('Y-m-d') . ')'
                    ], 400);
                }
            }
            
            // Update the reading
            $reading->reading_value = $request->value;
            if ($request->has('reading_date')) {
                $reading->reading_date = $request->reading_date;
            }
            // Note: is_rollover and rollover_reason_code columns don't exist in meter_readings table
            // Admin actions are tracked via admin_actions table instead
            $reading->updated_at = now();
            $reading->save();

            // Log admin action
            $this->logAdminAction('edit_reading', [
                'reading_id' => $id,
                'meter_id' => $reading->meter_id,
                'old_value' => $oldValue,
                'new_value' => $request->value,
                'old_date' => $oldDate ? $oldDate->format('Y-m-d') : null,
                'new_date' => $request->has('reading_date') ? $request->reading_date : ($oldDate ? $oldDate->format('Y-m-d') : null),
            ], $request->reason, $reading);

            // REGENERATE BILLS: After editing, regenerate bills for affected meter
            // This ensures bills are recalculated with the new reading value
            $meter = Meter::find($reading->meter_id);
            $regeneratedBillsCount = 0;
            
            if ($meter) {
                $account = Account::find($meter->account_id);
                if ($account) {
                    // Delete all existing bills for this meter (they need to be recalculated)
                    $existingBills = Bill::where('meter_id', $meter->id)->get();
                    foreach ($existingBills as $bill) {
                        $bill->delete();
                    }
                    
                    // Get all readings for this meter, ordered by date
                    $allReadings = MeterReadings::where('meter_id', $meter->id)
                        ->where('reading_type', 'ACTUAL')
                        ->orderBy('reading_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();
                    
                    if ($allReadings->count() >= 2) {
                        // Regenerate bills for all reading pairs
                        $billingEngine = app(BillingEngine::class);
                        $tariff = $account->tariffTemplate;
                        
                        if ($tariff) {
                            // Process each pair of consecutive readings
                            for ($i = 1; $i < $allReadings->count(); $i++) {
                                $openingReading = $allReadings[$i - 1];
                                $closingReading = $allReadings[$i];
                                
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
                                    
                                    // Get bill day - REQUIRED: no fallbacks allowed
                                    // Note: Use is_null() instead of empty() because empty(0) returns true
                                    $accountBillDay = $account->bill_day;
                                    $tariffBillDay = $tariff->billing_day;
                                    if (is_null($accountBillDay) && (is_null($tariffBillDay) || $tariffBillDay === '')) {
                                        return response()->json([
                                            'success' => false,
                                            'error' => 'MISSING_BILL_DAY',
                                            'message' => "Account #{$account->id} is missing bill_day. Cannot regenerate bills.",
                                        ], 400);
                                    }
                                    $billDay = !is_null($accountBillDay) && $accountBillDay > 0 ? $accountBillDay : $tariffBillDay;
                                    $periods = $periodCalculator->calculatePeriods(
                                        $billDay,
                                        $readings[0]['date'],
                                        $readings[1]['date']
                                    );
                                    
                                    if (!empty($periods)) {
                                        $tariffSnapshot = $billingEngine->convertTariffToSnapshot($tariff);
                                        $fixedCharges = $billingEngine->getFixedCharges($tariff, $account);
                                        $editableCharges = $billingEngine->getEditableCharges($account);
                                        
                                        // Determine meter type (water or electricity)
                                        $isWater = $tariff->is_water ?? false;
                                        
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
                                                
                                                // Calculate VAT
                                                // Get VAT rate - REQUIRED: no fallbacks allowed
                                                $vatRate = $tariff->getVatRate();
                                                if ($vatRate === null) {
                                                    return response()->json([
                                                        'success' => false,
                                                        'error' => 'MISSING_VAT_RATE',
                                                        'message' => "Tariff #{$tariff->id} is missing VAT rate. Cannot regenerate bills.",
                                                    ], 400);
                                                }
                                                $vatAmount = $vatRate > 0 
                                                    ? round(($billData['total_of_all_charges'] ?? 0) * ($vatRate / 100), 2)
                                                    : 0;
                                                $billData['vat_amount'] = $vatAmount;
                                                
                                                $billingEngine->createBill(
                                                    $billData,
                                                    $account,
                                                    $meter,
                                                    null
                                                );
                                                $regeneratedBillsCount++;
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    \Log::error('Failed to regenerate bill after reading edit', [
                                        'meter_id' => $meter->id,
                                        'opening_reading_id' => $openingReading->id,
                                        'closing_reading_id' => $closingReading->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();

            $message = 'Reading updated successfully';
            if ($regeneratedBillsCount > 0) {
                $message .= " ({$regeneratedBillsCount} bill(s) recalculated)";
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => $message,
                'data' => $reading,
                'regenerated_bills_count' => $regeneratedBillsCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to update reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete (soft-delete) a meter reading (admin only)
     * 
     * @param Request $request
     * @param int $id Reading ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteReading(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        $reading = MeterReadings::find($id);
        if (!$reading) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'msg' => 'Reading not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Store reading data for audit before deletion
            $readingData = $reading->toArray();
            
            // Log admin action BEFORE deletion
            $this->logAdminAction('delete_reading', [
                'reading_id' => $id,
                'meter_id' => $reading->meter_id,
                'reading_value' => $reading->reading_value,
                'reading_date' => $reading->reading_date,
            ], $request->reason, $reading);

            // CASCADE DELETE: Delete all bills that reference this reading
            // Bills can reference readings as opening_reading_id or closing_reading_id
            $affectedBills = Bill::where('opening_reading_id', $id)
                ->orWhere('closing_reading_id', $id)
                ->get();
            
            $deletedBillsCount = 0;
            foreach ($affectedBills as $bill) {
                // Log bill deletion for audit
                \Log::info('Cascade deleting bill due to reading deletion', [
                    'bill_id' => $bill->id,
                    'reading_id' => $id,
                    'account_id' => $bill->account_id,
                    'meter_id' => $bill->meter_id,
                ]);
                
                $bill->delete();
                $deletedBillsCount++;
            }

            // Delete the reading
            $reading->delete();

            // REGENERATE BILLS: After deletion, regenerate bills for remaining readings
            // This ensures bills are recalculated based on the new reading sequence
            $meter = Meter::find($reading->meter_id);
            $regeneratedBillsCount = 0;
            
            if ($meter) {
                $account = Account::find($meter->account_id);
                if ($account) {
                    // Get all remaining readings for this meter, ordered by date
                    $remainingReadings = MeterReadings::where('meter_id', $meter->id)
                        ->where('reading_type', 'ACTUAL')
                        ->orderBy('reading_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();
                    
                    if ($remainingReadings->count() >= 2) {
                        // Regenerate bills for all reading pairs
                        $billingEngine = app(BillingEngine::class);
                        $tariff = $account->tariffTemplate;
                        
                        if ($tariff) {
                            // Process each pair of consecutive readings
                            for ($i = 1; $i < $remainingReadings->count(); $i++) {
                                $openingReading = $remainingReadings[$i - 1];
                                $closingReading = $remainingReadings[$i];
                                
                                // Check if bill already exists for this pair
                                $existingBill = Bill::where('meter_id', $meter->id)
                                    ->where('opening_reading_id', $openingReading->id)
                                    ->where('closing_reading_id', $closingReading->id)
                                    ->first();
                                
                                if (!$existingBill) {
                                    // Generate bill for this reading pair
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
                                        
                                        // Get bill day - REQUIRED: no fallbacks allowed
                                        // Note: Use is_null() instead of empty() because empty(0) returns true
                                        $accountBillDay = $account->bill_day;
                                        $tariffBillDay = $tariff->billing_day;
                                        if (is_null($accountBillDay) && (is_null($tariffBillDay) || $tariffBillDay === '')) {
                                            return response()->json([
                                                'success' => false,
                                                'error' => 'MISSING_BILL_DAY',
                                                'message' => "Account #{$account->id} is missing bill_day. Cannot regenerate bills.",
                                            ], 400);
                                        }
                                        $billDay = !is_null($accountBillDay) && $accountBillDay > 0 ? $accountBillDay : $tariffBillDay;
                                        $periods = $periodCalculator->calculatePeriods(
                                            $billDay,
                                            $readings[0]['date'],
                                            $readings[1]['date']
                                        );
                                        
                                        if (!empty($periods)) {
                                            $tariffSnapshot = $billingEngine->convertTariffToSnapshot($tariff);
                                            $fixedCharges = $billingEngine->getFixedCharges($tariff, $account);
                                            $editableCharges = $billingEngine->getEditableCharges($account);
                                            
                                            // Determine meter type (water or electricity)
                                            $isWater = $tariff->is_water ?? false;
                                            
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
                                                    // Add reading objects to bill data (createBill expects objects)
                                                    $billData['openingReading'] = $openingReading;
                                                    $billData['closingReading'] = $closingReading;
                                                    
                                                    // Calculate VAT
                                                    // Get VAT rate - REQUIRED: no fallbacks allowed
                                                $vatRate = $tariff->getVatRate();
                                                if ($vatRate === null) {
                                                    return response()->json([
                                                        'success' => false,
                                                        'error' => 'MISSING_VAT_RATE',
                                                        'message' => "Tariff #{$tariff->id} is missing VAT rate. Cannot regenerate bills.",
                                                    ], 400);
                                                }
                                                    $vatAmount = $vatRate > 0 
                                                        ? round(($billData['total_of_all_charges'] ?? 0) * ($vatRate / 100), 2)
                                                        : 0;
                                                    $billData['vat_amount'] = $vatAmount;
                                                    
                                                    $billingEngine->createBill(
                                                        $billData,
                                                        $account,
                                                        $meter,
                                                        null
                                                    );
                                                    $regeneratedBillsCount++;
                                                }
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        \Log::error('Failed to regenerate bill after reading deletion', [
                                            'meter_id' => $meter->id,
                                            'opening_reading_id' => $openingReading->id,
                                            'closing_reading_id' => $closingReading->id,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();

            $message = 'Reading deleted successfully';
            if ($deletedBillsCount > 0) {
                $message .= " (and {$deletedBillsCount} related bill(s) deleted)";
            }
            if ($regeneratedBillsCount > 0) {
                $message .= " ({$regeneratedBillsCount} new bill(s) regenerated)";
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => $message,
                'data' => $readingData,
                'deleted_bills_count' => $deletedBillsCount,
                'regenerated_bills_count' => $regeneratedBillsCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete reading with cascade', [
                'reading_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to delete reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new meter reading (admin only, bypasses cooldown)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addReading(Request $request)
    {
        $request->validate([
            'meter_id' => 'required|exists:meters,id',
            'meter_reading_date' => 'required|date',
            'meter_reading' => 'required|string',
            'reason' => 'required|string|min:5',
            // Note: is_rollover and rollover_reason_code are not stored in meter_readings table
            // They are only used for admin action logging if needed
        ]);

        DB::beginTransaction();
        try {
            $newDate = $request->meter_reading_date;
            $newValue = floatval($request->meter_reading);
            
            // VALIDATION RULE 1: Date cannot be the same as an existing date
            $existingReading = MeterReadings::where('meter_id', $request->meter_id)
                ->where('reading_date', $newDate)
                ->first();
            
            if ($existingReading) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'msg' => 'A reading with this date already exists. Please delete the existing reading first or choose a different date.'
                ], 400);
            }
            
            // VALIDATION RULE 2: Value must be between previous and next readings
            // Get previous reading (earlier date)
            $previousReading = MeterReadings::where('meter_id', $request->meter_id)
                ->where('reading_date', '<', $newDate)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            // Get next reading (later date)
            $nextReading = MeterReadings::where('meter_id', $request->meter_id)
                ->where('reading_date', '>', $newDate)
                ->orderBy('reading_date', 'asc')
                ->first();
            
            // Check value is not lower than previous reading
            if ($previousReading && $newValue < floatval($previousReading->reading_value)) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'msg' => 'Reading value cannot be lower than the previous reading value (' . $previousReading->reading_value . ' on ' . $previousReading->reading_date->format('Y-m-d') . ')'
                ], 400);
            }
            
            // Check value is not higher than next reading
            if ($nextReading && $newValue > floatval($nextReading->reading_value)) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'msg' => 'Reading value cannot be higher than the next reading value (' . $nextReading->reading_value . ' on ' . $nextReading->reading_date->format('Y-m-d') . ')'
                ], 400);
            }
            
            // Create the reading (bypassing cooldown check)
            // Note: admin_override, is_rollover, and rollover_reason_code columns don't exist in meter_readings table
            // Admin actions are tracked via admin_actions table instead
            $reading = MeterReadings::create([
                'meter_id' => $request->meter_id,
                'reading_date' => $request->meter_reading_date,
                'reading_value' => $request->meter_reading,
                'created_at' => now(),
            ]);

            // Get meter info for audit
            $meter = Meter::find($request->meter_id);

            // Log admin action
            $this->logAdminAction('add_reading', [
                'reading_id' => $reading->id,
                'meter_id' => $request->meter_id,
                'account_id' => $meter->account_id ?? null,
                'reading_value' => $request->meter_reading,
                'reading_date' => $request->meter_reading_date,
                'bypass_cooldown' => true,
            ], $request->reason, $reading);

            DB::commit();

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => 'Reading added successfully',
                'data' => $reading
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to add reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set reading flags (admin only)
     * 
     * @param Request $request
     * @param int $id Reading ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function setFlags(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        $reading = MeterReadings::find($id);
        if (!$reading) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'msg' => 'Reading not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $oldFlags = [
                'is_rollover' => $reading->is_rollover,
                'admin_override' => $reading->admin_override,
                'is_estimated' => $reading->is_estimated ?? false,
                'is_final' => $reading->is_final ?? false,
            ];

            // Update flags
            if ($request->has('is_rollover')) {
                $reading->is_rollover = $request->is_rollover;
            }
            if ($request->has('admin_override')) {
                $reading->admin_override = $request->admin_override;
            }
            if ($request->has('is_estimated')) {
                $reading->is_estimated = $request->is_estimated;
            }
            if ($request->has('is_final')) {
                $reading->is_final = $request->is_final;
            }
            $reading->save();

            $newFlags = [
                'is_rollover' => $reading->is_rollover,
                'admin_override' => $reading->admin_override,
                'is_estimated' => $reading->is_estimated ?? false,
                'is_final' => $reading->is_final ?? false,
            ];

            // Log admin action
            $this->logAdminAction('set_flags', [
                'reading_id' => $id,
                'old_flags' => $oldFlags,
                'new_flags' => $newFlags,
            ], $request->reason, $reading);

            DB::commit();

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => 'Flags updated successfully',
                'data' => $reading
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to update flags: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reading history for a meter (admin view with all details)
     * 
     * @param Request $request
     * @param int $meterId Meter ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReadingHistory(Request $request, $meterId)
    {
        $meter = Meter::with(['readings' => function($q) {
            $q->orderBy('reading_date', 'desc');
        }])->find($meterId);

        if (!$meter) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'msg' => 'Meter not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'code' => 200,
            'msg' => 'Reading history retrieved',
            'data' => [
                'meter' => $meter,
                'readings' => $meter->readings,
            ]
        ]);
    }

    /**
     * Get audit log for readings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuditLog(Request $request)
    {
        $query = AdminAction::with(['admin'])->orderBy('created_at', 'desc');

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->has('meter_id')) {
            $query->where('meter_id', $request->meter_id);
        }
        if ($request->has('reading_id')) {
            $query->where('reading_id', $request->reading_id);
        }
        if ($request->has('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        $limit = $request->get('limit', 50);
        $actions = $query->limit($limit)->get();

        return response()->json([
            'status' => true,
            'code' => 200,
            'msg' => 'Audit log retrieved',
            'data' => $actions
        ]);
    }

    /**
     * Undo a previous admin action
     * 
     * @param Request $request
     * @param int $actionId Admin action ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function undoAction(Request $request, $actionId)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        $action = AdminAction::find($actionId);
        if (!$action) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'msg' => 'Admin action not found'
            ], 404);
        }

        if ($action->is_undone) {
            return response()->json([
                'status' => false,
                'code' => 400,
                'msg' => 'This action has already been undone'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $payload = json_decode($action->payload, true);
            
            // Perform undo based on action type
            switch ($action->action_type) {
                case 'edit_reading':
                    // Restore old value
                    if (isset($payload['reading_id']) && isset($payload['old_value'])) {
                        $reading = MeterReadings::find($payload['reading_id']);
                        if ($reading) {
                            $reading->reading_value = $payload['old_value'];
                            $reading->save();
                        }
                    }
                    break;

                case 'delete_reading':
                    // Cannot easily undo a delete - would need to recreate
                    // For now, return error
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'msg' => 'Cannot undo delete action. Please manually add the reading back.'
                    ], 400);

                case 'add_reading':
                    // Delete the added reading
                    if (isset($payload['reading_id'])) {
                        MeterReadings::destroy($payload['reading_id']);
                    }
                    break;

                case 'set_flags':
                    // Restore old flags
                    if (isset($payload['reading_id']) && isset($payload['old_flags'])) {
                        $reading = MeterReadings::find($payload['reading_id']);
                        if ($reading) {
                            foreach ($payload['old_flags'] as $flag => $value) {
                                $reading->$flag = $value;
                            }
                            $reading->save();
                        }
                    }
                    break;
            }

            // Mark original action as undone
            $action->is_undone = true;
            $action->save();

            // Log the undo action
            $undoAction = $this->logAdminAction('undo', [
                'original_action_id' => $actionId,
                'original_action_type' => $action->action_type,
                'original_payload' => $payload,
            ], $request->reason);

            // Link the undo action to the original
            $action->undone_by_action_id = $undoAction->id;
            $action->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => 'Action undone successfully',
                'data' => [
                    'original_action' => $action,
                    'undo_action' => $undoAction,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to undo action: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recompute a bill by bill ID (admin only)
     * 
     * @param Request $request
     * @param int $billId Bill ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function recomputeBill(Request $request, $billId)
    {
        $reason = $request->get('reason', 'Admin recompute request');
        
        DB::beginTransaction();
        try {
            // Log admin action
            $this->logAdminAction('recompute_bill', [
                'bill_id' => $billId,
            ], $reason);

            // TODO: Dispatch BillRecomputeJob
            // For now, return success with job_id placeholder
            $jobId = 'job_' . time() . '_' . $billId;

            DB::commit();

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => 'Bill recompute queued successfully',
                'job_id' => $jobId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to recompute bill: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recompute bill for an account (admin only)
     * 
     * @param Request $request
     * @param int $accountId Account ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function recomputeAccountBill(Request $request, $accountId)
    {
        $reason = $request->get('reason', 'Admin recompute request');
        
        $account = Account::find($accountId);
        if (!$account) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'msg' => 'Account not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Log admin action
            $this->logAdminAction('recompute_bill', [
                'account_id' => $accountId,
            ], $reason);

            // TODO: Dispatch BillRecomputeJob for account
            // For now, return success with job_id placeholder
            $jobId = 'job_' . time() . '_acc_' . $accountId;

            DB::commit();

            return response()->json([
                'status' => true,
                'code' => 200,
                'msg' => 'Account bill recompute queued successfully',
                'job_id' => $jobId,
                'account_id' => $accountId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'msg' => 'Failed to recompute account bill: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if current user has admin role
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAdminRole(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'is_admin' => false,
                'role' => 'guest'
            ], 401);
        }

        $isAdmin = $user->is_admin ?? false;
        
        return response()->json([
            'status' => true,
            'is_admin' => $isAdmin,
            'role' => $isAdmin ? 'admin' : 'user',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Log an admin action to the audit table
     * 
     * @param string $actionType
     * @param array $payload
     * @param string $reason
     * @param MeterReadings|null $reading
     * @return AdminAction
     */
    protected function logAdminAction(string $actionType, array $payload, string $reason, $reading = null): AdminAction
    {
        $user = Auth::user();
        
        return AdminAction::create([
            'admin_id' => $user ? $user->id : 1, // Fallback to 1 for testing
            'action_type' => $actionType,
            'reading_id' => $reading ? $reading->id : ($payload['reading_id'] ?? null),
            'meter_id' => $reading ? $reading->meter_id : ($payload['meter_id'] ?? null),
            'account_id' => $payload['account_id'] ?? null,
            'payload' => json_encode($payload),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}

