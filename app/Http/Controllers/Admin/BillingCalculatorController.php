<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\Site;
use App\Models\User;
// LEGACY DECOUPLING: Commented out to prevent initialization
// use App\Services\BillingCalculatorOrchestrator; // @deprecated - Use BillingCalculatorPeriodToPeriod instead
// use App\Services\BillingPeriodCalculator;
// use App\Services\TierBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Services\CalculatorPHP;
use Illuminate\Support\Facades\DB;
use App\Models\RegionsAccountTypeCost;

/**
 * BillingCalculatorController
 * 
 * Clean slate implementation based on:
 * https://github.com/SaleamEssop/BillingEngine/blob/main/BillingEngine1
 * 
 * UI structure is preserved - only backend logic is replaced.
 */
class BillingCalculatorController extends Controller
{
    // LEGACY DECOUPLING: Properties removed to prevent legacy service initialization
    // protected BillingCalculatorOrchestrator $orchestrator;
    // protected BillingPeriodCalculator $periodCalculator;
    // protected TierBillingService $tierService;

    public function __construct()
    {
        // LEGACY DECOUPLING: No dependency injection - CalculatorPHP is instantiated directly when needed
    }

    /**
     * Show the billing calculator page (Consolidated to new clean UI)
     */
    public function index()
    {
        return view('admin.calculator-php');
    }



    /**
     * Get tariff templates
     */
    public function getTariffTemplates(Request $request): JsonResponse
    {
        try {
            // Get all tariff templates from regions_account_type_cost (source of truth)
            // Include templates with water OR electricity OR both
            // Support PERIOD_TO_PERIOD, MONTHLY (backward compatibility), and null
            $templates = \App\Models\RegionsAccountTypeCost::with(['region'])
                ->where(function ($query) {
                    $query->where('is_water', 1)
                        ->orWhere('is_electricity', 1);
                })
                ->where(function ($query) {
                    $query->where('billing_type', 'PERIOD_TO_PERIOD')
                        ->orWhere('billing_type', 'MONTHLY')
                        ->orWhereNull('billing_type');
                })
                ->whereNotNull('template_name')
                ->where('template_name', '!=', '')
                ->orderBy('region_id')
                ->orderBy('template_name')
                ->get()
                ->map(function ($template) {
                    // Normalize billing_type: MONTHLY or null becomes PERIOD_TO_PERIOD
                    $billingType = $template->billing_type;
                    if ($billingType === 'MONTHLY' || $billingType === null) {
                        $billingType = 'PERIOD_TO_PERIOD';
                    }

                    return [
                        'id' => $template->id,
                        'name' => $template->template_name,
                        'region_id' => $template->region_id,
                        'region_name' => $template->region ? $template->region->name : null,
                        'billing_day' => $template->billing_day ?? null,
                        'billing_type' => $billingType,
                        'is_water' => (bool) $template->is_water,
                        'is_electricity' => (bool) $template->is_electricity,
                    ];
                });

            \Log::info('BillingCalculatorController::getTariffTemplates', [
                'count' => $templates->count(),
                'templates' => $templates->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            \Log::error('BillingCalculatorController::getTariffTemplates error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tariff template details
     */
    public function getTariffTemplateDetails(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'template_id' => 'required|integer|exists:regions_account_type_cost,id'
            ]);

            // Use RegionsAccountTypeCost (source of truth)
            $template = \App\Models\RegionsAccountTypeCost::with(['region'])
                ->findOrFail($request->input('template_id'));

            // Get tiers from water_in array (RegionsAccountTypeCost uses JSON column)
            $tiers = [];
            if (!empty($template->water_in) && is_array($template->water_in)) {
                foreach ($template->water_in as $tier) {
                    $tiers[] = [
                        'max' => isset($tier['max']) ? (float) $tier['max'] : null,
                        'rate' => isset($tier['cost']) ? (float) $tier['cost'] : (isset($tier['rate']) ? (float) $tier['rate'] : 0),
                        'min' => isset($tier['min']) ? (float) $tier['min'] : 0,
                    ];
                }
            }

            // Get fixed costs from fixed_costs array
            $fixedCosts = [];
            if (!empty($template->fixed_costs) && is_array($template->fixed_costs)) {
                foreach ($template->fixed_costs as $cost) {
                    if (isset($cost['name']) && isset($cost['value'])) {
                        $fixedCosts[] = [
                            'name' => $cost['name'],
                            'value' => (float) $cost['value'],
                        ];
                    }
                }
            }

            // Get customer costs
            $customerCosts = [];
            if (!empty($template->customer_costs) && is_array($template->customer_costs)) {
                foreach ($template->customer_costs as $cost) {
                    if (isset($cost['name']) && isset($cost['value'])) {
                        $customerCosts[] = [
                            'name' => $cost['name'],
                            'value' => (float) $cost['value'],
                        ];
                    }
                }
            }

            // Get water in additional charges
            $waterInAdditional = [];
            if (!empty($template->waterin_additional) && is_array($template->waterin_additional)) {
                foreach ($template->waterin_additional as $charge) {
                    $waterInAdditional[] = [
                        'title' => $charge['title'] ?? $charge['name'] ?? 'Additional Charge',
                        'cost' => isset($charge['cost']) ? (float) $charge['cost'] : 0,
                        'percentage' => isset($charge['percentage']) ? (float) $charge['percentage'] : null,
                    ];
                }
            }

            // Get water out tiers
            $waterOutTiers = [];
            if (!empty($template->water_out) && is_array($template->water_out)) {
                foreach ($template->water_out as $tier) {
                    $waterOutTiers[] = [
                        'min' => isset($tier['min']) ? (float) $tier['min'] : 0,
                        'max' => isset($tier['max']) ? (float) $tier['max'] : null,
                        'cost' => isset($tier['cost']) ? (float) $tier['cost'] : (isset($tier['rate']) ? (float) $tier['rate'] : 0),
                        'percentage' => isset($tier['percentage']) ? (float) $tier['percentage'] : 100,
                    ];
                }
            }

            // Get water out additional charges
            $waterOutAdditional = [];
            if (!empty($template->waterout_additional) && is_array($template->waterout_additional)) {
                foreach ($template->waterout_additional as $charge) {
                    $waterOutAdditional[] = [
                        'title' => $charge['title'] ?? $charge['name'] ?? 'Additional Charge',
                        'cost' => isset($charge['cost']) ? (float) $charge['cost'] : 0,
                        'percentage' => isset($charge['percentage']) ? (float) $charge['percentage'] : null,
                    ];
                }
            }

            // Get VAT rate
            $vatRate = $template->vat_rate ?? $template->vat_percentage ?? 15.0;

            // Normalize billing_type
            $billingType = $template->billing_type;
            if ($billingType === 'MONTHLY' || $billingType === null) {
                $billingType = 'PERIOD_TO_PERIOD';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $template->id,
                    'name' => $template->template_name,
                    'billing_type' => $billingType,
                    'tiers' => $tiers,
                    'water_out' => $waterOutTiers,
                    'fixed_costs' => $fixedCosts,
                    'customer_costs' => $customerCosts,
                    'waterin_additional' => $waterInAdditional,
                    'waterout_additional' => $waterOutAdditional,
                    'vat_rate' => $vatRate,
                    'billing_day' => $template->billing_day ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching template details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reconcile periods when new readings arrive
     * 
     * REFACTORED (v2030): Method body commented out - uses legacy tierService.
     * Reconciliation will be reimplemented using CalculatorPHP in next version.
     * CalculatorPHP::computePeriod() already handles reconciliation internally.
     */
    public function reconcilePeriod(Request $request): JsonResponse
    {
        // COMMENTED OUT (v2030): This method used legacy orchestrator and tierService
        // Reconciliation is now handled automatically by CalculatorPHP::computePeriod()
        // which calls reconcileProvisionalBills() internally

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation is now handled automatically by CalculatorPHP::computePeriod(). This endpoint is deprecated.',
            'data' => [
                'reconciliation_required' => false,
                'message' => 'Please use CalculatorPHP::computePeriod() which handles reconciliation automatically.',
            ]
        ]);

        /* ORIGINAL CODE (COMMENTED OUT - v2030):
        $request->validate([
            'readings' => 'required|array',
            'provisioned_periods' => 'required|array',
            'bill_day' => 'required|integer|min:1|max:31',
            'tiers' => 'required|array',
        ]);

        try {
            // ... original implementation using $this->orchestrator and $this->tierService ...
        } catch (\Exception $e) {
            // ... error handling ...
        }
        */
    }

    /**
     * Generate bill
     */
    public function generateBill(Request $request): JsonResponse
    {
        // Placeholder - can be implemented later
        return response()->json([
            'success' => true,
            'message' => 'Bill generation not yet implemented'
        ]);
    }

    /**
     * Get users with their accounts
     * GET /admin/billing-calculator/users
     */
    public function getUsers(): JsonResponse
    {
        try {
            $users = User::with([
                'sites.accounts' => function ($query) {
                    $query->select('id', 'site_id', 'account_name');
                }
            ])->get();

            $usersData = $users->map(function ($user) {
                $accounts = [];
                foreach ($user->sites as $site) {
                    foreach ($site->accounts as $account) {
                        $accounts[] = [
                            'id' => $account->id,
                            'account_name' => $account->account_name,
                        ];
                    }
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'accounts' => $accounts
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $usersData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account details with meters, readings, and bills
     * GET /admin/billing-calculator/account/{id}
     */
    public function getAccountDetails($id): JsonResponse
    {
        try {
            $account = Account::with([
                'site.user',
                'site.region',
                'tariffTemplate',
                'meters.readings' => function ($query) {
                    $query->orderBy('reading_date', 'asc');
                }
            ])->findOrFail($id);

            // Format meters with readings
            $meters = $account->meters->map(function ($meter) {
                return [
                    'id' => $meter->id,
                    'meter_title' => $meter->meter_title,
                    'meter_number' => $meter->meter_number,
                    'readings' => $meter->readings->map(function ($reading) {
                        return [
                            'id' => $reading->id,
                            'date' => $reading->reading_date->format('Y-m-d'),
                            'value' => (float) $reading->reading_value,
                            'type' => $reading->reading_type ?? 'ACTUAL',
                        ];
                    })->toArray()
                ];
            })->toArray();

            // Get bills for this account
            $bills = Bill::where('account_id', $account->id)
                ->orderBy('period_start_date', 'desc')
                ->get()
                ->map(function ($bill) {
                    return [
                        'id' => $bill->id,
                        'period_start_date' => $bill->period_start_date ? $bill->period_start_date->format('Y-m-d') : null,
                        'period_end_date' => $bill->period_end_date ? $bill->period_end_date->format('Y-m-d') : null,
                        'status' => $bill->bill_total_status ?? 'PROVISIONAL',
                        'total_amount' => (float) $bill->total_amount,
                        'consumption' => (float) $bill->consumption,
                    ];
                })->toArray();

            // Get last finalized period
            $lastFinalizedBill = Bill::where('account_id', $account->id)
                ->where('bill_total_status', 'ACTUAL')
                ->orderBy('period_end_date', 'desc')
                ->first();

            $lastFinalizedPeriod = $lastFinalizedBill && $lastFinalizedBill->period_end_date
                ? $lastFinalizedBill->period_end_date->format('Y-m-d')
                : null;

            // Get customer cost overrides for this account
            $customerCosts = DB::table('customer_cost_overrides')
                ->where('account_id', $account->id)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $account->site->user->id,
                        'name' => $account->site->user->name,
                        'email' => $account->site->user->email,
                        'contact_number' => $account->site->user->contact_number,
                    ],
                    'site' => [
                        'id' => $account->site->id,
                        'title' => $account->site->title,
                        'address' => $account->site->address,
                        'region' => $account->site->region ? $account->site->region->name : null,
                    ],
                    'account' => [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                        'account_number' => $account->account_number,
                        'name_on_bill' => $account->name_on_bill,
                        'bill_day' => $account->bill_day,
                        'tariff_template' => $account->tariffTemplate ? [
                            'id' => $account->tariffTemplate->id,
                            'name' => $account->tariffTemplate->template_name,
                            'billing_type' => $account->tariffTemplate->billing_type,
                            'billing_day' => $account->tariffTemplate->billing_day,
                        ] : null,
                    ],
                    'tariff' => $account->tariffTemplate ? RegionsAccountTypeCost::find($account->tariff_template_id) : null,
                    'customer_cost_overrides' => $customerCosts,
                    'meters' => $meters,
                    'raw_bills' => $bills,
                    'bills' => $bills,
                    'last_finalized_period' => $lastFinalizedPeriod,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching account details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a test bill for CalculatorPHP testing (TEST MODE)
     * 
     * SILENCE PROTOCOL: All DB operations wrapped in withoutEvents() to bypass observers.
     * DATA INTEGRITY: Validates readings via CalculatorPHP Section 4 before persistence.
     * 
     * POST /admin/billing-calculator/create-test-bill
     */
    public function createTestBill(Request $request): JsonResponse
    {
        try {
            // Direct Validation: tariff_templates ONLY (no legacy tables)
            $request->validate([
                'tariff_template_id' => 'required|exists:regions_account_type_cost,id',
                'period_start_date' => 'required|date',
                'period_end_date' => 'required|date|after:period_start_date',
                'readings' => 'nullable|array',
                'readings.*.date' => 'required_with:readings|date',
                'readings.*.value' => 'required_with:readings|numeric|min:0',
                'start_reading' => 'nullable|numeric|min:0',
                'start_reading_date' => 'nullable|date',
            ]);

            // DATA INTEGRITY: Validate readings before persistence
            if ($request->has('readings') && is_array($request->readings) && count($request->readings) > 1) {
                $readings = collect($request->readings)->sortBy('date');
                $lastValue = null;

                foreach ($readings as $reading) {
                    $currentValue = (float) $reading['value'];

                    if ($lastValue !== null && $currentValue < $lastValue) {
                        throw ValidationException::withMessages([
                            'readings' => "Reading values must be monotonic (increasing). Found: {$lastValue} > {$currentValue}."
                        ]);
                    }

                    $lastValue = $currentValue;
                }
            }

            // ENSURE DATA INTEGRITY: Satisfaction of foreign key constraints (accounts, meter_types)
            $site = \App\Models\Site::first() ?: \App\Models\Site::create(['name' => 'Simulation Site']);
            $account = \App\Models\Account::first() ?: \App\Models\Account::create([
                'site_id' => $site->id,
                'account_name' => 'Simulation Account',
                'account_number' => 'SIM-' . time(),
            ]);
            $accountId = $account->id;

            // Determine meter type from template
            $template = \App\Models\RegionsAccountTypeCost::find($request->tariff_template_id);
            $meterTypeId = ($template->is_electricity && !$template->is_water) ? 2 : 1;

            // SILENCE PROTOCOL: Wrap all DB operations in withoutEvents()
            $testMeterId = null;
            $billId = null;

            \App\Models\MeterReadings::withoutEvents(function () use ($request, $accountId, $meterTypeId, &$testMeterId) {
                // Create temporary test meter (bypasses any meter observers)
                // Include start_reading and start_reading_date for Period 1 initialization
                $meterData = [
                    'meter_title' => 'Test Meter (CalculatorPHP)',
                    'meter_number' => 'TEST-' . time(),
                    'account_id' => $accountId,
                    'meter_type_id' => $meterTypeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                // Add start_reading and start_reading_date if provided (for Period 1)
                if ($request->has('start_reading') && $request->start_reading !== null) {
                    $meterData['start_reading'] = $request->input('start_reading');
                }
                if ($request->has('start_reading_date') && $request->start_reading_date !== null) {
                    $meterData['start_reading_date'] = $request->input('start_reading_date');
                }
                
                $testMeterId = DB::table('meters')->insertGetId($meterData);

                // Insert test readings (bypasses MeterReadingObserver)
                if ($request->has('readings') && is_array($request->readings)) {
                    foreach ($request->readings as $reading) {
                        DB::table('meter_readings')->insert([
                            'meter_id' => $testMeterId,
                            'reading_date' => $reading['date'],
                            'reading_value' => $reading['value'],
                            'reading_type' => 'ACTUAL',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            // Create test bill (bypasses Bill model events)
            \App\Models\Bill::withoutEvents(function () use ($request, $accountId, $testMeterId, &$billId) {
                $billId = DB::table('bills')->insertGetId([
                    'account_id' => $accountId,
                    'meter_id' => $testMeterId,
                    'tariff_template_id' => $request->tariff_template_id, // Verified mapping
                    'period_start_date' => $request->period_start_date,
                    'period_end_date' => $request->period_end_date,
                    'consumption' => 0,
                    'tiered_charge' => 0,
                    'total_amount' => 0,
                    'status' => 'PROVISIONAL',
                    'is_provisional' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            \Log::info('CalculatorPHP Test Bill Created (Silence Protocol)', [
                'bill_id' => $billId,
                'meter_id' => $testMeterId,
                'tariff_template_id' => $request->tariff_template_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test bill created successfully',
                'data' => [
                    'bill_id' => $billId, // Exact ID passed to computePeriod
                    'meter_id' => $testMeterId,
                ]
            ]);
        } catch (ValidationException $e) {
            // STRICT ERROR HANDLING: Return 422 with specific Physics error
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('CalculatorPHP Create Test Bill Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating test bill: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compute Period - Wrapper for CalculatorPHP::computePeriod()
     * 
     * POST /admin/billing-calculator/compute-period
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function computePeriod(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'bill_id' => 'required|exists:bills,id',
            ]);

            $billId = $request->input('bill_id');

            // Use CalculatorPHP to compute the period
            $calculator = new CalculatorPHP();
            $result = $calculator->computePeriod((int) $billId);

            return response()->json($result);
        } catch (ValidationException $e) {
            // STRICT ERROR HANDLING: Return 422 with Physics validation errors
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('BillingCalculatorController::computePeriod error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Dual-run test: Compare JavaScript and PHP calculator outputs
     * 
     * POST /admin/billing-calculator/api/dual-run-test
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dualRunTest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'test_case_id' => 'required|string',
                'inputs' => 'required|array',
                'js_output' => 'required|array',
                'context' => 'nullable|array'
            ]);

            $testCaseId = $request->input('test_case_id');
            $inputs = $request->input('inputs');
            $jsOutput = $request->input('js_output');
            $context = $request->input('context', []);

            // Execute dual-run comparison
            $dualRun = new \App\Services\BillingCalculatorDualRun();
            $result = $dualRun->execute($inputs, $jsOutput, $testCaseId, $context);

            return response()->json([
                'success' => true,
                'parity_status' => $result['parity_status'],
                'diff_count' => $result['diff_count'],
                'diffs' => $result['diffs'],
                'test_case_id' => $result['test_case_id'],
                'context' => $result['context'],
                'php_output' => $result['php_output'] ?? null,
                'js_output' => $result['js_output'] ?? null
            ]);

        } catch (\Exception $e) {
            \Log::channel('api')->error('Dual-run test error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dual-run test error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search accounts by email, name, or account number
     * GET /admin/billing-calculator/search-accounts?q={query}
     */
    public function searchAccounts(Request $request): JsonResponse
    {
        $query = $request->input('q');

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'accounts' => []
            ]);
        }

        $accounts = Account::with(['site.user', 'site'])
            ->where(function ($q) use ($query) {
                $q->where('account_name', 'LIKE', "%{$query}%")
                    ->orWhere('account_number', 'LIKE', "%{$query}%")
                    ->orWhereHas('site.user', function ($userQuery) use ($query) {
                        $userQuery->where('email', 'LIKE', "%{$query}%")
                            ->orWhere('name', 'LIKE', "%{$query}%")
                            ->orWhere('contact_number', 'LIKE', "%{$query}%");
                    })
                    ->orWhereHas('site', function ($siteQuery) use ($query) {
                        $siteQuery->where('address', 'LIKE', "%{$query}%")
                            ->orWhere('suburb', 'LIKE', "%{$query}%");
                    });
            })
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }



    /**
     * Get all bills for an account with tier breakdowns
     * GET /admin/billing-calculator/account/{accountId}/bills
     */
    public function getAccountBills($accountId): JsonResponse
    {
        $account = Account::find($accountId);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $bills = Bill::where('account_id', $accountId)
            ->with(['meter'])
            ->orderBy('period_start_date', 'desc')
            ->get();

        // Enhance each bill with tier breakdown and daily metrics
        $enhancedBills = $bills->map(function ($bill) use ($account) {
            $tariff = RegionsAccountTypeCost::find($account->tariff_template_id);

            // Calculate tier breakdown
            $tierBreakdown = [];
            if ($tariff && $bill->consumption) {
                $tiers = $tariff->water_in ?? [];
                $remainingUsage = $bill->consumption;
                $totalCost = 0;

                foreach ($tiers as $tier) {
                    if ($remainingUsage <= 0)
                        break;

                    $tierMin = $tier['min'] ?? 0;
                    $tierMax = $tier['max'] ?? PHP_INT_MAX;
                    $tierRate = $tier['amount'] ?? 0;

                    $tierUsage = min($remainingUsage, $tierMax - $tierMin);
                    $tierCost = ($tierUsage / 1000) * $tierRate; // Convert L to kL

                    $tierBreakdown[] = [
                        'min' => $tierMin,
                        'max' => $tierMax,
                        'rate' => $tierRate,
                        'usage' => $tierUsage,
                        'cost' => $tierCost
                    ];

                    $totalCost += $tierCost;
                    $remainingUsage -= $tierUsage;
                }
            }

            // Calculate daily metrics
            $periodStart = Carbon::parse($bill->period_start_date);
            $periodEnd = Carbon::parse($bill->period_end_date);
            $daysInPeriod = $periodStart->diffInDays($periodEnd) + 1; // Inclusive

            $dailyUsage = $daysInPeriod > 0 ? round($bill->consumption / $daysInPeriod, 2) : 0;
            $dailyCost = $daysInPeriod > 0 ? round($bill->tiered_charge / $daysInPeriod, 2) : 0;

            // Totals
            $fixedCostsTotal = array_sum(array_column($tariff->fixed_costs ?? [], 'value'));
            $customerCostsTotal = array_sum(array_column($tariff->customer_costs ?? [], 'value'));

            // Check for overrides
            $overrides = DB::table('customer_cost_overrides')->where('account_id', $account->id)->get();
            if ($overrides->isNotEmpty()) {
                $customerCostsTotal = $overrides->sum('value');
            }

            $subtotal = $bill->tiered_charge + $fixedCostsTotal + $customerCostsTotal;
            $vat = $subtotal * 0.15;
            $total = $subtotal + $vat;

            return [
                'id' => $bill->id,
                'period_start_date' => $bill->period_start_date,
                'period_end_date' => $bill->period_end_date,
                'opening_reading' => $bill->opening_reading,
                'closing_reading' => $bill->closing_reading,
                'consumption' => $bill->consumption,
                'tiered_charge' => $bill->tiered_charge,
                'fixed_costs' => $fixedCostsTotal,
                'customer_costs' => $customerCostsTotal,
                'status' => $bill->status,
                'days_in_period' => $daysInPeriod,
                'daily_usage' => $dailyUsage,
                'daily_cost' => $dailyCost,
                'subtotal' => $subtotal,
                'vat' => $vat,
                'total_amount' => $total,
                'projected_total' => $total,
                'tier_breakdown' => $tierBreakdown
            ];
        });

        return response()->json([
            'success' => true,
            'bills' => $enhancedBills
        ]);
    }

    /**
     * Update customer cost override for account
     * POST /admin/billing-calculator/account/{accountId}/customer-costs
     */
    public function updateCustomerCosts(Request $request, $accountId): JsonResponse
    {
        $request->validate([
            'costs' => 'required|array',
            'costs.*.name' => 'required|string',
            'costs.*.value' => 'required|numeric'
        ]);

        $account = Account::find($accountId);
        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        foreach ($request->costs as $cost) {
            DB::table('customer_cost_overrides')->updateOrInsert(
                [
                    'account_id' => $accountId,
                    'charge_name' => $cost['name']
                ],
                [
                    'value' => $cost['value'],
                    'updated_at' => now()
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer costs updated successfully'
        ]);
    }

    /**
     * Calculate billing periods for an account.
     * Used by UserAccountSetupController during test-user creation/validation.
     *
     * POST /admin/billing-calculator/api/calculate-periods
     *
     * @param Request $request  { account_id: int }
     * @return JsonResponse     { success: bool, data: { periods: [{start, end, billable_days}] } }
     */
    public function calculatePeriods(Request $request): JsonResponse
    {
        try {
            $accountId = $request->input('account_id');
            if (!$accountId) {
                return response()->json(['success' => false, 'error' => 'account_id is required'], 422);
            }

            $account = Account::with(['meters.readings'])->find($accountId);
            if (!$account) {
                return response()->json(['success' => false, 'error' => 'Account not found'], 404);
            }

            $billDay = (int) ($account->bill_day ?? 15);

            // Collect all reading dates across all meters
            $allDates = [];
            foreach ($account->meters as $meter) {
                foreach ($meter->readings as $reading) {
                    $date = $reading->reading_date ?? $reading->date ?? null;
                    if ($date) {
                        $allDates[] = $date;
                    }
                }
            }

            if (empty($allDates)) {
                return response()->json([
                    'success' => true,
                    'data'    => ['periods' => []],
                ]);
            }

            sort($allDates);
            $startDate = reset($allDates);
            $endDate   = end($allDates);

            $calculator = new \App\Services\BillingPeriodCalculator();
            $periods    = $calculator->calculatePeriods($billDay, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data'    => ['periods' => $periods],
            ]);
        } catch (\Exception $e) {
            \Log::error('BillingCalculatorController::calculatePeriods error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
