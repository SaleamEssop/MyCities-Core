<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\RegionsAccountTypeCost;
use App\Models\User;
use App\Services\CalculatorPHP;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * AccountBillingCalculatorController
 * 
 * Production billing calculator with account management features:
 * - Search users by email, phone, account number
 * - Select account and meter
 * - View full bill or add readings
 * - Add/remove accounts and meters
 * 
 * This is the production version that will sync features from the test calculator
 * when they are validated.
 */
class AccountBillingCalculatorController extends Controller
{
    protected CalculatorPHP $billingService;

    public function __construct(CalculatorPHP $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Show the account billing calculator page
     */
    public function index()
    {
        return view('admin.account-billing-calculator');
    }

    /**
     * Search users by email, phone, or account number
     * GET /admin/account-billing-calculator/search
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $query = $request->input('query', '');
            
            if (empty($query)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $users = User::with([
                'sites.accounts' => function($q) {
                    $q->with('tariffTemplate:id,template_name,billing_type');
                },
                'sites.accounts.meters' => function($q) {
                    $q->withCount('readings');
                }
            ])
            ->where(function($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                  ->orWhere('contact_number', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            })
            ->orWhereHas('sites.accounts', function($q) use ($query) {
                $q->where('account_number', 'like', "%{$query}%")
                  ->orWhere('account_name', 'like', "%{$query}%");
            })
            ->get()
            ->map(function($user) {
                $accounts = [];
                foreach ($user->sites as $site) {
                    foreach ($site->accounts as $account) {
                        $accounts[] = [
                            'id' => $account->id,
                            'account_name' => $account->account_name,
                            'account_number' => $account->account_number,
                            'site_id' => $site->id,
                            'site_title' => $site->title,
                            'tariff_template_id' => $account->tariff_template_id,
                            'tariff_name' => $account->tariffTemplate ? $account->tariffTemplate->template_name : null,
                            'billing_type' => $account->tariffTemplate ? $account->tariffTemplate->billing_type : null,
                            'meters' => $account->meters->map(function($meter) {
                                return [
                                    'id' => $meter->id,
                                    'meter_title' => $meter->meter_title,
                                    'meter_number' => $meter->meter_number,
                                    'readings_count' => $meter->readings_count ?? 0
                                ];
                            })
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
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account details with meters and readings
     * GET /admin/account-billing-calculator/account/{id}
     */
    public function getAccount($id): JsonResponse
    {
        try {
            $account = Account::with([
                'site.user',
                'tariffTemplate',
                'meters.readings' => function($query) {
                    $query->orderBy('reading_date', 'asc');
                }
            ])->findOrFail($id);

            $meters = $account->meters->map(function($meter) {
                return [
                    'id' => $meter->id,
                    'meter_title' => $meter->meter_title,
                    'meter_number' => $meter->meter_number,
                    'readings' => $meter->readings->map(function($reading) {
                        return [
                            'id' => $reading->id,
                            'date' => $reading->reading_date->format('Y-m-d'),
                            'value' => (float) $reading->reading_value,
                            'type' => $reading->reading_type ?? 'ACTUAL',
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                        'account_number' => $account->account_number,
                        'bill_day' => $account->bill_day,
                        'tariff_template_id' => $account->tariff_template_id,
                        'tariff_name' => $account->tariffTemplate ? $account->tariffTemplate->template_name : null,
                        'billing_type' => $account->tariffTemplate ? $account->tariffTemplate->billing_type : null,
                    ],
                    'user' => [
                        'id' => $account->site->user->id,
                        'name' => $account->site->user->name,
                        'email' => $account->site->user->email,
                    ],
                    'site' => [
                        'id' => $account->site->id,
                        'title' => $account->site->title,
                    ],
                    'meters' => $meters
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate bill for an account
     * POST /admin/account-billing-calculator/calculate
     */
    public function calculateBill(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'account_id' => 'required|integer|exists:accounts,id',
                'meter_id' => 'required|integer|exists:meters,id',
                'billing_mode' => 'required|in:PERIOD_TO_PERIOD,DATE_TO_DATE',
                'bill_day' => 'nullable|integer|min:1|max:31',
                'start_date' => 'nullable|date',
            ]);

            $account = Account::with('tariffTemplate')->findOrFail($request->input('account_id'));
            $meter = Meter::findOrFail($request->input('meter_id'));

            // Get readings for this meter
            $readings = MeterReadings::where('meter_id', $meter->id)
                ->orderBy('reading_date', 'asc')
                ->get()
                ->map(function($reading) {
                    return [
                        'date' => $reading->reading_date->format('Y-m-d'),
                        'value' => (float) $reading->reading_value,
                        'type' => $reading->reading_type ?? 'ACTUAL',
                    ];
                })
                ->toArray();

            if (count($readings) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least 2 readings are required to calculate a bill'
                ], 400);
            }

            $tariff = $account->tariffTemplate;
            if (!$tariff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account has no tariff template assigned'
                ], 400);
            }

            // Determine billing mode from tariff if not provided
            $billingMode = $request->input('billing_mode');
            if (!$billingMode) {
                $billingMode = ($tariff->billing_type === 'DATE_TO_DATE') ? 'DATE_TO_DATE' : 'PERIOD_TO_PERIOD';
            }

            // Calculate bill
            $options = [];
            if ($request->has('bill_day')) {
                $options['bill_day'] = $request->input('bill_day');
            } elseif ($account->bill_day) {
                $options['bill_day'] = $account->bill_day;
            } elseif ($tariff->billing_day) {
                $options['bill_day'] = $tariff->billing_day;
            }

            if ($request->has('start_date')) {
                $options['start_date'] = $request->input('start_date');
            }

            if ($billingMode === 'PERIOD_TO_PERIOD') {
                $usageData = $this->billingService->calculatePeriodToPeriod($readings, $tariff, $options);
            } else {
                $usageData = $this->billingService->calculateDateToDate($readings, $tariff, $options);
            }

            // Calculate complete bill
            $bill = $this->billingService->calculateBill($usageData, $tariff);

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                    ],
                    'meter' => [
                        'id' => $meter->id,
                        'meter_title' => $meter->meter_title,
                    ],
                    'usage_data' => $usageData,
                    'bill' => $bill,
                    'billing_mode' => $billingMode
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating bill: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a reading to a meter
     * POST /admin/account-billing-calculator/reading
     */
    public function addReading(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'meter_id' => 'required|integer|exists:meters,id',
                'reading_date' => 'required|date',
                'reading_value' => 'required|numeric|min:0',
                'reading_type' => 'nullable|in:ACTUAL,CALCULATED,PROVISIONAL',
            ]);

            $meter = Meter::findOrFail($request->input('meter_id'));

            // Check if reading already exists for this date
            $existing = MeterReadings::where('meter_id', $meter->id)
                ->whereDate('reading_date', $request->input('reading_date'))
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'A reading already exists for this date'
                ], 400);
            }

            $reading = MeterReadings::create([
                'meter_id' => $meter->id,
                'reading_date' => $request->input('reading_date'),
                'reading_value' => $request->input('reading_value'),
                'reading_type' => $request->input('reading_type', 'ACTUAL'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reading added successfully',
                'data' => [
                    'id' => $reading->id,
                    'date' => $reading->reading_date->format('Y-m-d'),
                    'value' => (float) $reading->reading_value,
                    'type' => $reading->reading_type,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a reading
     * DELETE /admin/account-billing-calculator/reading/{id}
     */
    public function deleteReading($id): JsonResponse
    {
        try {
            $reading = MeterReadings::findOrFail($id);
            $reading->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reading deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tariff templates (for dropdown)
     * GET /admin/account-billing-calculator/tariff-templates
     */
    public function getTariffTemplates(Request $request): JsonResponse
    {
        try {
            $billingType = $request->input('billing_type'); // Optional filter

            // Include templates with water OR electricity OR both
            $query = RegionsAccountTypeCost::where(function($q) {
                    $q->where('is_water', 1)
                      ->orWhere('is_electricity', 1);
                })
                ->whereNotNull('template_name')
                ->where('template_name', '!=', '');

            if ($billingType) {
                // Support both PERIOD_TO_PERIOD and MONTHLY (backward compatibility)
                if ($billingType === 'PERIOD_TO_PERIOD') {
                    $query->where(function($q) {
                        $q->where('billing_type', 'PERIOD_TO_PERIOD')
                          ->orWhere('billing_type', 'MONTHLY')
                          ->orWhereNull('billing_type');
                    });
                } else {
                    $query->where('billing_type', $billingType);
                }
            }

            $templates = $query->orderBy('template_name')->get()->map(function($template) {
                // Normalize billing_type: MONTHLY or null becomes PERIOD_TO_PERIOD
                $billingType = $template->billing_type;
                if ($billingType === 'MONTHLY' || $billingType === null) {
                    $billingType = 'PERIOD_TO_PERIOD';
                }
                
                return [
                    'id' => $template->id,
                    'name' => $template->template_name,
                    'billing_type' => $billingType,
                    'is_water' => (bool) $template->is_water,
                    'is_electricity' => (bool) $template->is_electricity,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading tariff templates: ' . $e->getMessage()
            ], 500);
        }
    }
}










