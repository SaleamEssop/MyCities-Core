<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\MeterType;
use App\Models\Payment;
use App\Models\Regions;
use App\Models\RegionsAccountTypeCost;
use App\Models\Settings;
use App\Models\Site;
use App\Models\User;
// LEGACY DECOUPLING: Commented out - services moved to LegacyQuarantine
// use App\Services\BillingEngine;
// use App\Services\DateToDatePeriodCalculator;
// use App\Services\ReadingEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAccountManagerController extends Controller
{
    // No default constants allowed - meter_category_id can be null

    /**
     * Display the manager dashboard
     */
    public function index()
    {
        $settings = Settings::first();
        $demoMode = $settings->demo_mode ?? true;

        if ($demoMode) {
            // Demo mode: show all users including demo accounts
            $users = User::withCount('sites')
                ->with([
                    'sites.region', 
                    'sites.accounts' => function($query) {
                        $query->select('id', 'site_id', 'account_name', 'bill_day', 'tariff_template_id')
                              ->with('tariffTemplate:id,billing_type')
                              ->limit(1);
                    }
                ])
                ->get()
                ->map(function($user) {
                    // Get first account ID
                    $firstAccountId = null;
                    $hasValidationIssues = false;
                    
                    foreach ($user->sites as $site) {
                        if ($site->accounts && $site->accounts->count() > 0) {
                            $account = $site->accounts->first();
                            $firstAccountId = $account->id;
                            
                            // Check for validation issues
                            if ($account->tariffTemplate) {
                                if ($account->tariffTemplate->isMonthlyBilling() && empty($account->bill_day)) {
                                    $hasValidationIssues = true;
                                }
                            }
                            break;
                        }
                    }
                    
                    // Convert to array and add metadata
                    $userArray = $user->toArray();
                    $userArray['first_account_id'] = $firstAccountId;
                    $userArray['has_validation_issues'] = $hasValidationIssues;
                    return $userArray;
                });
        } else {
            // Production mode: show all users (demo users are just ordinary users)
            $users = User::withCount('sites')
                ->with([
                    'sites.region', 
                    'sites.accounts' => function($query) {
                        $query->select('id', 'site_id', 'account_name', 'bill_day', 'tariff_template_id')
                              ->with('tariffTemplate:id,billing_type')
                              ->limit(1);
                    }
                ])
                ->get()
                ->map(function($user) {
                    // Get first account ID
                    $firstAccountId = null;
                    $hasValidationIssues = false;
                    
                    foreach ($user->sites as $site) {
                        if ($site->accounts && $site->accounts->count() > 0) {
                            $account = $site->accounts->first();
                            $firstAccountId = $account->id;
                            
                            // Check for validation issues
                            if ($account->tariffTemplate) {
                                if ($account->tariffTemplate->isMonthlyBilling() && empty($account->bill_day)) {
                                    $hasValidationIssues = true;
                                }
                            }
                            break;
                        }
                    }
                    
                    // Convert to array and add metadata
                    $userArray = $user->toArray();
                    $userArray['first_account_id'] = $firstAccountId;
                    $userArray['has_validation_issues'] = $hasValidationIssues;
                    return $userArray;
                });
        }
        
        $regions = Regions::all();
        $meterTypes = MeterType::all();
        
        return view('admin.user-accounts.manager', [
            'users' => $users,
            'regions' => $regions,
            'meterTypes' => $meterTypes,
        ]);
    }

    /**
     * Display the webapp embedded view for an account (mimics the Vue/Quasar webapp)
     * Route: /admin/user-accounts/manager/webApp/{accountId}
     */
    public function showWebApp($accountId)
    {
        $account = Account::with(['tariffTemplate', 'meters.readings', 'site.user', 'defaultFixedCosts.fixedCost'])->findOrFail($accountId);
        $site = $account->site;
        $user = $site ? $site->user : null;
        
        if (!$user) {
            abort(404, 'User not found for this account');
        }

        return view('admin.webapp-embed', [
            'account' => $account,
            'user' => $user,
            'site' => $site,
        ]);
    }

    /**
     * Display the webapp for a user (simplified route - finds first account automatically)
     * Route: /admin/user-webapp/{userId}
     */
    public function showUserWebApp($userId)
    {
        $user = User::with(['sites.accounts.tariffTemplate', 'sites.accounts.meters.readings', 'sites.accounts.defaultFixedCosts.fixedCost'])->findOrFail($userId);
        
        // Find first account
        $account = null;
        foreach ($user->sites as $site) {
            if ($site->accounts && $site->accounts->count() > 0) {
                $account = $site->accounts->first();
                break;
            }
        }
        
        if (!$account) {
            abort(404, 'No accounts found for this user. Please create an account first.');
        }

        return view('admin.webapp-embed', [
            'account' => $account,
            'user' => $user,
            'site' => $account->site,
        ]);
    }

    /**
     * Display the account billing page (Blade-based, non-Vue)
     */
    public function showAccountBilling($accountId)
    {
        $account = Account::with(['tariffTemplate', 'meters.readings', 'site.user'])->findOrFail($accountId);
        $site = $account->site;
        $user = $site ? $site->user : User::find($account->user_id);
        $tariff = $account->tariffTemplate;
        // LEGACY DECOUPLING: BillingEngine moved to LegacyQuarantine
        $billingEngine = null;
        try {
            if (file_exists(app_path('Services/BillingEngine.php')) || 
                file_exists(app_path('Services/LegacyQuarantine/BillingEngine.php'))) {
                // Only try to resolve if file exists (even in quarantine)
                // LEGACY DECOUPLING: BillingEngine moved to LegacyQuarantine
            $billingEngine = null;
            try {
                if (file_exists(app_path('Services/BillingEngine.php')) || 
                    file_exists(app_path('Services/LegacyQuarantine/BillingEngine.php'))) {
                    $billingEngine = app(\App\Services\BillingEngine::class);
                }
            } catch (\Exception $e) {
                $billingEngine = null;
            }
            }
        } catch (\Exception $e) {
            $billingEngine = null; // Legacy service quarantined - functionality disabled
        }

        // Get all readings sorted by date
        $allReadings = collect();
        foreach ($account->meters as $meter) {
            foreach ($meter->readings as $reading) {
                $allReadings->push([
                    'meter_id' => $meter->id,
                    'meter_type' => $meter->meterTypes?->title ?? 'Unknown',
                    'reading' => $reading,
                ]);
            }
        }
        $allReadings = $allReadings->sortBy(fn($r) => $r['reading']->reading_date);

        // Get all payments
        $allPayments = Payment::where('account_id', $accountId)
            ->orderBy('payment_date', 'asc')
            ->get();

        // Build billing periods from consecutive readings
        $periods = [];
        $previousReading = null;
        $runningBalance = 0;

        foreach ($allReadings as $readingData) {
            $reading = $readingData['reading'];
            
            if ($previousReading) {
                // reading_date is cast as 'date' in model, so it's always Carbon when retrieved
                $prevDate = $previousReading['reading']->reading_date;
                $currDate = $reading->reading_date;
                $days = $prevDate->diffInDays($currDate);

                // Calculate consumption charge for this period
                $result = $billingEngine->calculateCharge($account, $previousReading['reading'], $reading);
                $consumptionCharge = $result->tieredCharge;
                
                // Add VAT
                $vatRate = $tariff ? $tariff->getVatRate() : 15;
                $vatAmount = $consumptionCharge * ($vatRate / 100);
                $periodTotal = $consumptionCharge + $vatAmount;

                // Get payments within this period
                $periodPayments = $allPayments->filter(function($p) use ($prevDate, $currDate) {
                    $payDate = \Carbon\Carbon::parse($p->payment_date);
                    return $payDate->gte($prevDate) && $payDate->lte($currDate);
                })->map(fn($p) => [
                    'id' => $p->id,
                    'date' => $p->payment_date->format('Y-m-d'),
                    'amount' => (float) $p->amount,
                    'method' => $p->payment_method,
                    'reference' => $p->reference,
                ])->values()->toArray();

                $totalPayments = collect($periodPayments)->sum('amount');
                
                // Calculate balance for this period
                $balanceBF = $runningBalance > 0 ? $runningBalance : 0;
                $periodOwing = $periodTotal + $balanceBF;
                $balance = $periodOwing - $totalPayments;
                $runningBalance = $balance;

                $periods[] = [
                    'start_date' => $prevDate->format('Y-m-d'),
                    'end_date' => $currDate->format('Y-m-d'),
                    'days' => $days,
                    'consumption_charge' => round($consumptionCharge + $vatAmount, 2),
                    'balance_bf' => round($balanceBF, 2),
                    'period_total' => round($periodOwing, 2),
                    'payments' => $periodPayments,
                    'total_payments' => round($totalPayments, 2),
                    'balance' => round($balance, 2),
                ];
            }

            $previousReading = $readingData;
        }

        // Reverse to show most recent first
        $periods = array_reverse($periods);

        // Calculate total owing (current balance from last period)
        $totalOwing = count($periods) > 0 ? $periods[0]['balance'] : 0;

        // Also count payments made AFTER the last period (recent payments)
        $lastPeriodEnd = count($periods) > 0 ? $periods[0]['end_date'] : null;
        $recentPayments = [];
        
        if ($lastPeriodEnd) {
            $recentPayments = $allPayments->filter(function($p) use ($lastPeriodEnd) {
                return \Carbon\Carbon::parse($p->payment_date)->gt(\Carbon\Carbon::parse($lastPeriodEnd));
            })->map(fn($p) => [
                'id' => $p->id,
                'date' => $p->payment_date->format('Y-m-d'),
                'amount' => (float) $p->amount,
                'method' => $p->payment_method,
                'reference' => $p->reference,
            ])->values()->toArray();
            
            $recentPaymentsTotal = collect($recentPayments)->sum('amount');
            $totalOwing = $totalOwing - $recentPaymentsTotal;
        }

        return view('admin.account-billing', [
            'account' => $account,
            'user' => $user,
            'site' => $site,
            'tariff' => $tariff,
            'periods' => $periods,
            'totalOwing' => $totalOwing,
            'recentPayments' => $recentPayments,
        ]);
    }

    /**
     * Search users with filters
     */
    public function search(Request $request)
    {
        $query = User::withCount('sites')
            ->with([
                'sites.accounts' => function($q) {
                    $q->select('id', 'site_id', 'account_name', 'bill_day', 'tariff_template_id')
                      ->with('tariffTemplate:id,billing_type')
                      ->limit(1);
                }
            ]);

        // Unified search via ?q= parameter (name, email, phone)
        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('contact_number', 'like', $term);
            });
        }
        
        // Search by name
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        
        // Search by address (through sites)
        if ($request->filled('address')) {
            $query->whereHas('sites', function($q) use ($request) {
                $q->where('address', 'like', '%' . $request->address . '%');
            });
        }
        
        // Search by phone
        if ($request->filled('phone')) {
            $query->where('contact_number', 'like', '%' . $request->phone . '%');
        }
        
        // Filter by user type
        if ($request->filled('user_type')) {
            if ($request->user_type === 'test') {
                $query->where('email', 'like', '%@test.com');
            } elseif ($request->user_type === 'real') {
                $query->where('email', 'not like', '%@test.com');
            }
        }
        
        $users = $query->get()->map(function($user) {
            // Get first account ID
            $firstAccountId = null;
            $hasValidationIssues = false;
            
            foreach ($user->sites as $site) {
                if ($site->accounts && $site->accounts->count() > 0) {
                    $account = $site->accounts->first();
                    $firstAccountId = $account->id;
                    
                    // Check for validation issues
                    if ($account->tariffTemplate) {
                        if ($account->tariffTemplate->isMonthlyBilling() && empty($account->bill_day)) {
                            $hasValidationIssues = true;
                        }
                    }
                    break;
                }
            }
            
            // Convert to array and add metadata
            $userArray = $user->toArray();
            $userArray['first_account_id'] = $firstAccountId;
            $userArray['has_validation_issues'] = $hasValidationIssues;
            return $userArray;
        });
        
        return response()->json([
            'status' => 200,
            'data' => $users
        ]);
    }

    /**
     * Get user data with all related entities for editing
     */
    public function getUserData($id)
    {
        $user = User::with([
            'sites.accounts.meters.readings' => function($query) {
                $query->orderBy('reading_date', 'desc');
            },
            'sites.accounts.tariffTemplate',
            'sites.region'
        ])->find($id);
        
        if (!$user) {
            return response()->json(['status' => 404, 'message' => 'User not found']);
        }
        
        // Add validation flags for missing mandatory fields
        $userData = $user->toArray();
        $userData['validation_issues'] = $this->validateUserMandatoryFields($user);
        
        return response()->json(['status' => 200, 'data' => $userData]);
    }
    
    /**
     * Validate user's mandatory fields based on billing type
     */
    private function validateUserMandatoryFields(User $user): array
    {
        $issues = [];
        
        foreach ($user->sites as $site) {
            foreach ($site->accounts as $account) {
                $accountIssues = [];
                
                // Check if account has a tariff template
                if (!$account->tariffTemplate) {
                    $accountIssues[] = [
                        'type' => 'missing_tariff',
                        'severity' => 'error',
                        'message' => 'Account has no tariff template assigned'
                    ];
                } else {
                    $tariff = $account->tariffTemplate;
                    
                    // If monthly billing, bill_day is mandatory
                    if ($tariff->isMonthlyBilling()) {
                        if (empty($account->bill_day)) {
                            $accountIssues[] = [
                                'type' => 'missing_bill_day',
                                'severity' => 'error',
                                'message' => 'Monthly billing requires billing day to be set',
                                'account_id' => $account->id,
                                'account_name' => $account->account_name
                            ];
                        }
                    }
                    // Date-to-date billing doesn't require bill_day
                }
                
                if (!empty($accountIssues)) {
                    $issues[] = [
                        'site_id' => $site->id,
                        'site_title' => $site->title,
                        'account_id' => $account->id,
                        'account_name' => $account->account_name,
                        'issues' => $accountIssues
                    ];
                }
            }
        }
        
        return $issues;
    }

    /**
     * Update user basic details
     */
    public function updateUser(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'contact_number' => 'required|string|max:20',
        ]);

        try {
            $user = User::findOrFail($id);
            
            $user->name = $request->name;
            $user->email = $request->email;
            $user->contact_number = $request->contact_number;
            
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            
            $user->save();
            
            return response()->json([
                'status' => 200, 
                'message' => 'User updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500, 
                'message' => 'Error updating user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update account details
     */
    public function updateAccount(Request $request, $id)
    {
        try {
            $account = Account::findOrFail($id);
            
            // Update editable fields (only if provided)
            if ($request->has('account_name')) {
                $account->account_name = $request->account_name;
            }
            // Validate name_on_bill is required
            if ($request->has('name_on_bill')) {
                if (empty($request->name_on_bill) || trim($request->name_on_bill) === '') {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Name on Bill is a mandatory field and cannot be empty.'
                    ], 400);
                }
                $account->name_on_bill = $request->name_on_bill;
            } else {
                // If not provided and account doesn't have one, return error
                if (empty($account->name_on_bill)) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Name on Bill is a mandatory field and must be provided.'
                    ], 400);
                }
            }
            if ($request->has('water_email')) {
                $account->water_email = $request->water_email;
            }
            if ($request->has('electricity_email')) {
                $account->electricity_email = $request->electricity_email;
            }
            if ($request->has('bill_day')) {
                $account->bill_day = $request->bill_day;
            }
            if ($request->has('read_day')) {
                $account->read_day = $request->read_day;
            }
            if ($request->has('billing_date')) {
                $account->billing_date = $request->billing_date;
            }
            
            // Handle customer editable costs (stored as JSON)
            if ($request->has('customer_costs')) {
                $account->customer_costs = $request->customer_costs;
            }
            
            $account->save();
            
            return response()->json([
                'status' => 200, 
                'message' => 'Account updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500, 
                'message' => 'Error updating account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a meter to an account
     */
    public function addMeter(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'meter_type_id' => 'required|exists:meter_types,id',
            'meter_title' => 'required|string|max:255',
            'meter_number' => 'required|string|max:50',
        ]);

        try {
            $meter = Meter::create([
                'account_id' => $request->account_id,
                'meter_type_id' => $request->meter_type_id,
                'meter_category_id' => $request->meter_category_id, // Can be null
                'meter_title' => $request->meter_title,
                'meter_number' => $request->meter_number,
            ]);
            
            // Add initial reading if provided
            if ($request->filled('initial_reading')) {
                MeterReadings::create([
                    'meter_id' => $meter->id,
                    'reading_date' => $request->initial_reading_date ?? now()->format('Y-m-d'),
                    'reading_value' => $request->initial_reading,
                ]);
            }
            
            return response()->json([
                'status' => 200, 
                'message' => 'Meter added successfully',
                'meter_id' => $meter->id
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500, 
                'message' => 'Error adding meter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update meter details
     */
    public function updateMeter(Request $request, $id)
    {
        $request->validate([
            'meter_title' => 'required|string|max:255',
            'meter_number' => 'required|string|max:50',
        ]);

        try {
            $meter = Meter::findOrFail($id);
            
            $meter->meter_title = $request->meter_title;
            $meter->meter_number = $request->meter_number;
            $meter->meter_type_id = $request->meter_type_id;
            
            $meter->save();
            
            return response()->json([
                'status' => 200, 
                'message' => 'Meter updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500, 
                'message' => 'Error updating meter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a meter
     */
    public function deleteMeter($id)
    {
        try {
            $meter = Meter::findOrFail($id);
            $meter->delete();
            
            return response()->json([
                'status' => 200, 
                'message' => 'Meter deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500, 
                'message' => 'Error deleting meter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a reading to a meter with validation and trigger billing calculation
     * 
     * UPDATED: Now uses ReadingEntryService to handle reading entry and billing calculation
     */
    public function addReading(Request $request)
    {
        $request->validate([
            'meter_id' => 'required|exists:meters,id',
            'reading_date' => 'required|date',
            'reading_value' => 'required|string', // String to preserve leading zeros for water meters
        ]);

        try {
            $meter = Meter::findOrFail($request->meter_id);
            $newDate = $request->reading_date;
            $newValue = floatval($request->reading_value);
            
            // Use ReadingEntryService to add reading and trigger billing
            $readingEntryService = app(ReadingEntryService::class);
            $result = $readingEntryService->addReading($meter, $newDate, $newValue);
            
            return response()->json([
                'status' => 200, 
                'message' => 'Reading added successfully and billing calculated',
                'reading_id' => $result['reading']->id,
                'billing_mode' => $result['billing_mode'],
                'billing' => $result['billing'],
            ]);
            
        } catch (\InvalidArgumentException $e) {
            // Validation errors from ReadingEntryService
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Error adding reading via ReadingEntryService', [
                'meter_id' => $request->meter_id,
                'date' => $request->reading_date,
                'value' => $request->reading_value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'status' => 500, 
                'message' => 'Error adding reading: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get readings history for a meter
     */
    public function getReadings($meterId)
    {
        $readings = MeterReadings::where('meter_id', $meterId)
            ->orderBy('reading_date', 'desc')
            ->get();
        
        return response()->json([
            'status' => 200,
            'data' => $readings
        ]);
    }

    /**
     * Delete a user and all related data completely
     */
    public function deleteUser($id)
    {
        DB::beginTransaction();
        
        try {
            $user = User::with(['sites.accounts.meters.readings', 'sites.accounts.payments'])->findOrFail($id);
            
            // Log what will be deleted
            $deletionSummary = [
                'sites' => $user->sites->count(),
                'accounts' => 0,
                'meters' => 0,
                'readings' => 0,
                'payments' => 0,
            ];
            
            foreach ($user->sites as $site) {
                $deletionSummary['accounts'] += $site->accounts->count();
                foreach ($site->accounts as $account) {
                    $deletionSummary['meters'] += $account->meters->count();
                    $deletionSummary['payments'] += $account->payments->count();
                    foreach ($account->meters as $meter) {
                        $deletionSummary['readings'] += $meter->readings->count();
                    }
                }
            }
            
            // Delete user (cascade will handle the rest)
            $user->delete();
            
            // Also delete any API tokens
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $id)
                ->delete();
            
            DB::commit();
            
            return response()->json([
                'status' => 200, 
                'message' => "User '{$user->name}' and all associated data deleted successfully. Removed: {$deletionSummary['sites']} site(s), {$deletionSummary['accounts']} account(s), {$deletionSummary['meters']} meter(s), {$deletionSummary['readings']} reading(s), {$deletionSummary['payments']} payment(s)."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500, 
                'message' => 'Error deleting user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tariff templates by region
     */
    public function getTariffTemplatesByRegion($regionId)
    {
        $templates = RegionsAccountTypeCost::where('region_id', $regionId)
            ->where('is_active', 1)
            ->get(['id', 'template_name', 'is_water', 'is_electricity', 'start_date', 'end_date']);
        
        return response()->json([
            'status' => 200,
            'data' => $templates
        ]);
    }

    /**
     * Get account billing summary with payments
     */
    public function getAccountBilling($accountId)
    {
        try {
            $account = Account::with(['tariffTemplate', 'meters.readings'])->findOrFail($accountId);
            $tariff = $account->tariffTemplate;
            
            // Get billing period dates from meter readings
            $allReadings = collect();
            foreach ($account->meters as $meter) {
                $allReadings = $allReadings->merge($meter->readings);
            }
            $allReadings = $allReadings->sortBy('reading_date');
            
            // Format dates as simple Y-m-d strings
            $firstReading = $allReadings->first();
            $lastReading = $allReadings->last();
            // reading_date is cast as 'date' in model, so it's always Carbon when retrieved
            $periodStart = $firstReading ? $firstReading->reading_date->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d');
            $periodEnd = $lastReading ? $lastReading->reading_date->format('Y-m-d') : now()->format('Y-m-d');
            
            // Get payments for this period
            $payments = Payment::where('account_id', $accountId)
                ->whereBetween('payment_date', [$periodStart, $periodEnd])
                ->orderBy('payment_date', 'desc')
                ->get();
            
            // Calculate consumption and charges using billing engine
            // LEGACY DECOUPLING: BillingEngine moved to LegacyQuarantine
        // $billingEngine = app(BillingEngine::class);
        $billingEngine = null; // Legacy service quarantined - functionality disabled
            $totalCharge = 0;
            $consumptionBreakdown = [];
            
            foreach ($account->meters as $meter) {
                $readings = $meter->readings->sortBy('reading_date');
                $openingReading = $readings->first();
                $closingReading = $readings->last();
                
                if ($openingReading && $closingReading && $openingReading->id !== $closingReading->id) {
                    $result = $billingEngine->calculateCharge($account, $openingReading, $closingReading);
                    $totalCharge += $result->tieredCharge;
                    
                    $consumptionBreakdown[] = [
                        'meter' => $meter->meter_title,
                        'type' => $meter->meterTypes?->title ?? 'Unknown',
                        'opening' => $openingReading->reading_value,
                        'closing' => $closingReading->reading_value,
                        'consumption' => $result->consumption,
                        'charge' => $result->tieredCharge,
                    ];
                }
            }
            
            // Calculate VAT and total
            $vatRate = $tariff ? $tariff->getVatRate() : 15;
            $vatAmount = $totalCharge * ($vatRate / 100);
            $grandTotal = $totalCharge + $vatAmount;
            $totalPaid = $payments->sum('amount');
            $balanceDue = $grandTotal - $totalPaid;
            
            return response()->json([
                'status' => 200,
                'data' => [
                    'account' => [
                        'id' => $account->id,
                        'name' => $account->account_name,
                        'number' => $account->account_number,
                    ],
                    'tariff' => $tariff ? [
                        'name' => $tariff->template_name,
                        'billing_type' => $tariff->billing_type ?? 'MONTHLY',
                        'vat_rate' => $vatRate,
                    ] : null,
                    'period' => [
                        'start' => $periodStart,
                        'end' => $periodEnd,
                    ],
                    'consumption' => $consumptionBreakdown,
                    'payments' => $payments->map(fn($p) => [
                        'id' => $p->id,
                        'amount' => (float) $p->amount,
                        'date' => $p->payment_date->format('Y-m-d'),
                        'method' => $p->payment_method,
                        'reference' => $p->reference,
                        'notes' => $p->notes,
                    ])->toArray(),
                    'totals' => [
                        'consumption' => round($totalCharge, 2),
                        'vat' => round($vatAmount, 2),
                        'grand_total' => round($grandTotal, 2),
                        'total_paid' => round($totalPaid, 2),
                        'balance_due' => round($balanceDue, 2),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching billing data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a payment for an account
     */
    public function addPayment(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = Payment::create([
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method ?? 'EFT',
                'reference' => $request->reference ?? 'PAY-' . time(),
                'notes' => $request->notes,
            ]);

            // If it's a form submission (not AJAX), redirect back
            if (!$request->expectsJson()) {
                return redirect()->route('user-accounts.billing', ['accountId' => $request->account_id])
                    ->with('success', 'Payment of R' . number_format($payment->amount, 2) . ' recorded successfully!');
            }

            return response()->json([
                'status' => 200,
                'message' => 'Payment added successfully',
                'data' => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'date' => $payment->payment_date->format('Y-m-d'),
                    'method' => $payment->payment_method,
                    'reference' => $payment->reference,
                ],
            ]);
        } catch (\Exception $e) {
            if (!$request->expectsJson()) {
                return redirect()->back()->with('error', 'Error adding payment: ' . $e->getMessage());
            }
            
            return response()->json([
                'status' => 500,
                'message' => 'Error adding payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a payment
     */
    public function deletePayment($id)
    {
        try {
            $payment = Payment::findOrFail($id);
            $payment->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Payment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error deleting payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get billing history with periods for mobile preview
     */
    public function getBillingHistory($accountId)
    {
        try {
            $account = Account::with(['tariffTemplate', 'meters.readings'])->findOrFail($accountId);
            $user = User::find($account->user_id);
            $tariff = $account->tariffTemplate;
            // LEGACY DECOUPLING: BillingEngine moved to LegacyQuarantine
        // $billingEngine = app(BillingEngine::class);
        $billingEngine = null; // Legacy service quarantined - functionality disabled

            // Get all readings sorted by date
            $allReadings = collect();
            foreach ($account->meters as $meter) {
                foreach ($meter->readings as $reading) {
                    $allReadings->push([
                        'meter_id' => $meter->id,
                        'meter_type' => $meter->meterTypes?->title ?? 'Unknown',
                        'reading' => $reading,
                    ]);
                }
            }
            $allReadings = $allReadings->sortBy(fn($r) => $r['reading']->reading_date);

            // Get all payments
            $allPayments = Payment::where('account_id', $accountId)
                ->orderBy('payment_date', 'asc')
                ->get();

            // Build billing periods from consecutive readings
            $periods = [];
            $previousReading = null;
            $runningBalance = 0;

            foreach ($allReadings as $readingData) {
                $reading = $readingData['reading'];
                
                if ($previousReading) {
                    // reading_date is cast as 'date' in model, so it's always Carbon when retrieved
                    $prevDate = $previousReading['reading']->reading_date;
                    $currDate = $reading->reading_date;
                    $days = $prevDate->diffInDays($currDate);

                    // Calculate consumption charge for this period
                    $result = $billingEngine->calculateCharge($account, $previousReading['reading'], $reading);
                    $consumptionCharge = $result->tieredCharge;
                    
                    // Add VAT
                    $vatRate = $tariff ? $tariff->getVatRate() : 15;
                    $vatAmount = $consumptionCharge * ($vatRate / 100);
                    $periodTotal = $consumptionCharge + $vatAmount;

                    // Get payments within this period
                    $periodPayments = $allPayments->filter(function($p) use ($prevDate, $currDate) {
                        $payDate = \Carbon\Carbon::parse($p->payment_date);
                        return $payDate->gte($prevDate) && $payDate->lte($currDate);
                    })->map(fn($p) => [
                        'id' => $p->id,
                        'date' => $p->payment_date->format('Y-m-d'),
                        'amount' => (float) $p->amount,
                        'method' => $p->payment_method,
                        'reference' => $p->reference,
                    ])->values()->toArray();

                    $totalPayments = collect($periodPayments)->sum('amount');
                    
                    // Calculate balance for this period
                    $balanceBF = $runningBalance > 0 ? $runningBalance : 0;
                    $periodOwing = $periodTotal + $balanceBF;
                    $balance = $periodOwing - $totalPayments;
                    $runningBalance = $balance;

                    $periods[] = [
                        'start_date' => $prevDate->format('Y-m-d'),
                        'end_date' => $currDate->format('Y-m-d'),
                        'days' => $days,
                        'consumption_charge' => round($consumptionCharge + $vatAmount, 2),
                        'balance_bf' => round($balanceBF, 2),
                        'period_total' => round($periodOwing, 2),
                        'payments' => $periodPayments,
                        'total_payments' => round($totalPayments, 2),
                        'balance' => round($balance, 2),
                    ];
                }

                $previousReading = $readingData;
            }

            // Reverse to show most recent first
            $periods = array_reverse($periods);

            // Calculate total owing (current balance)
            $totalOwing = count($periods) > 0 ? $periods[0]['balance'] : 0;

            return response()->json([
                'status' => 200,
                'data' => [
                    'user_name' => $user ? $user->name : 'Unknown',
                    'account_name' => $account->account_name,
                    'account_number' => $account->account_number,
                    'total_owing' => max(0, $totalOwing),
                    'periods' => $periods,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching billing history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show Laravel Blade dashboard for a user account
     * This is a native Laravel implementation of the Vue dashboard
     */
    public function showDashboard($accountId)
    {
        try {
            $account = Account::with(['site.user', 'tariffTemplate', 'meters.meterTypes'])->findOrFail($accountId);
            
            // Get dashboard data using the same logic as the API
            $billingController = new \App\Http\Controllers\Api\BillingController(
                app(\App\Repositories\BillingRepository::class),
                app(\App\Services\DashboardService::class)
            );
            
            // Create a mock request with accountId
            $request = new \Illuminate\Http\Request();
            $request->merge(['accountId' => $accountId]);
            
            // Set authenticated user as admin for this request
            \Illuminate\Support\Facades\Auth::loginUsingId(\Illuminate\Support\Facades\Auth::id());
            
            // Get dashboard data
            $dashboardResponse = $billingController->getDashboard($request);
            $dashboardData = json_decode($dashboardResponse->getContent(), true);
            
            // Extract errors if present (dashboard may still open with errors)
            $errors = [];
            if (isset($dashboardData['has_errors']) && $dashboardData['has_errors']) {
                $errors = $dashboardData['errors'] ?? [];
            }
            
            // If no success and no errors array, it's a real failure
            if ((!$dashboardData || !isset($dashboardData['success']) || !$dashboardData['success']) && empty($errors)) {
                return redirect()->route('user-accounts.manager')
                    ->with('alert-class', 'alert-danger')
                    ->with('alert-message', $dashboardData['message'] ?? 'Failed to load dashboard data');
            }
            
            // Use data from response, or empty structure if not available
            $dashboardDataArray = $dashboardData['data'] ?? [
                'account' => ['id' => $account->id, 'account_name' => $account->account_name],
                'site' => ['id' => $account->site->id ?? null, 'title' => $account->site->title ?? 'Unknown'],
                'tariff' => ['id' => $account->tariffTemplate->id ?? null, 'name' => $account->tariffTemplate->template_name ?? 'Unknown'],
                'water' => ['enabled' => false, 'meter' => null, 'readings' => [], 'consumption' => 0, 'charges' => ['total' => 0, 'breakdown' => []]],
                'electricity' => ['enabled' => false, 'meter' => null, 'readings' => [], 'consumption' => 0, 'charges' => ['total' => 0, 'breakdown' => []]],
                'period' => null,
                'payments' => [],
                'totals' => ['consumption_total' => 0, 'vat_amount' => 0, 'grand_total' => 0, 'total_paid' => 0, 'balance_due' => 0],
            ];
            
            // Get billing history for period navigation
            // Temporarily set the authenticated user to the account's user for history retrieval
            $originalUser = \Illuminate\Support\Facades\Auth::user();
            \Illuminate\Support\Facades\Auth::loginUsingId($account->site->user_id);
            
            try {
                $historyResponse = $billingController->getBillingHistoryForUser();
                $historyData = json_decode($historyResponse->getContent(), true);
                $allPeriods = $historyData['data']['periods'] ?? [];
            } finally {
                // Restore original authenticated user
                if ($originalUser) {
                    \Illuminate\Support\Facades\Auth::loginUsingId($originalUser->id);
                }
            }
            
            // Get period index from query (0 = current, 1+ = past periods)
            $periodIndex = request()->get('period', 0);
            
            // Store errors in session for persistence across page reloads
            if (!empty($errors)) {
                session(['dashboard_errors' => $errors]);
            } else {
                // Check if errors exist in session (from previous load)
                $sessionErrors = session('dashboard_errors', []);
                if (!empty($sessionErrors)) {
                    $errors = $sessionErrors;
                }
            }
            
            return view('admin.user-accounts.dashboard', [
                'account' => $account,
                'dashboardData' => $dashboardDataArray,
                'allPeriods' => $allPeriods,
                'currentPeriodIndex' => (int) $periodIndex,
                'errors' => $errors, // Pass errors to view for persistent display
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard view error', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('user-accounts.manager')
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Show Laravel Readings page for a user account
     */
    public function showReadings($accountId)
    {
        try {
            $account = Account::with(['site.user', 'meters.meterTypes', 'tariffTemplate'])->findOrFail($accountId);
            
            \Log::info('Readings page - Starting', [
                'account_id' => $accountId,
                'account_name' => $account->account_name,
                'has_site' => $account->site !== null,
                'has_user' => $account->site && $account->site->user !== null,
            ]);
            
            // Get meter data using the same logic as the API
            // Use dependency injection to get BillingController properly
            $billingController = app(\App\Http\Controllers\Api\BillingController::class);
            
            $request = new \Illuminate\Http\Request();
            $request->merge(['accountId' => $accountId]);
            
            // Ensure we're authenticated as admin for the API call
            $currentUser = \Illuminate\Support\Facades\Auth::user();
            if (!$currentUser) {
                \Log::error('Readings page - No authenticated user', [
                    'account_id' => $accountId,
                ]);
                
                return view('admin.user-accounts.readings', [
                    'account' => $account,
                    'meterData' => [
                        'water' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'electricity' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'account' => ['name' => $account->account_name ?? ''],
                    ],
                    'periodInfo' => [],
                    'error' => 'You must be logged in to view readings',
                ]);
            }
            
            // Get dashboard data to extract meter info
            try {
                $dashboardResponse = $billingController->getDashboard($request);
                $dashboardData = json_decode($dashboardResponse->getContent(), true);
                
                // Log for debugging
                \Log::info('Readings - Dashboard API response', [
                    'account_id' => $accountId,
                    'status_code' => $dashboardResponse->getStatusCode(),
                    'success' => $dashboardData['success'] ?? false,
                    'has_data' => isset($dashboardData['data']),
                    'has_water' => isset($dashboardData['data']['water']),
                    'has_electricity' => isset($dashboardData['data']['electricity']),
                ]);
            } catch (\Exception $apiException) {
                \Log::error('Readings - Dashboard API exception', [
                    'account_id' => $accountId,
                    'error' => $apiException->getMessage(),
                    'file' => $apiException->getFile(),
                    'line' => $apiException->getLine(),
                    'trace' => $apiException->getTraceAsString(),
                ]);
                
                // Return view with error instead of throwing
                return view('admin.user-accounts.readings', [
                    'account' => $account,
                    'meterData' => [
                        'water' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'electricity' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'account' => ['name' => $account->account_name ?? ''],
                    ],
                    'periodInfo' => [],
                    'error' => 'Failed to load meter data: ' . $apiException->getMessage(),
                ]);
            }
            
            // Check if API call was successful
            if (!$dashboardData || !isset($dashboardData['success']) || !$dashboardData['success']) {
                $errorMessage = $dashboardData['message'] ?? 'Failed to load readings data';
                \Log::error('Readings - Dashboard API failed', [
                    'account_id' => $accountId,
                    'response' => $dashboardData,
                ]);
                
                // Return view with empty data structure to prevent errors
                return view('admin.user-accounts.readings', [
                    'account' => $account,
                    'meterData' => [
                        'water' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'electricity' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'account' => ['name' => $account->account_name ?? ''],
                    ],
                    'periodInfo' => [],
                    'error' => $errorMessage,
                ]);
            }
            
            // CRITICAL: Check if 'data' key exists before accessing it
            if (!isset($dashboardData['data']) || !is_array($dashboardData['data'])) {
                \Log::error('Readings - Dashboard API missing data key', [
                    'account_id' => $accountId,
                    'response_keys' => array_keys($dashboardData),
                    'response' => $dashboardData,
                ]);
                
                return view('admin.user-accounts.readings', [
                    'account' => $account,
                    'meterData' => [
                        'water' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'electricity' => ['enabled' => false, 'meter' => null, 'readings' => []],
                        'account' => ['name' => $account->account_name ?? ''],
                    ],
                    'periodInfo' => [],
                    'error' => 'Invalid data structure received from API',
                ]);
            }
            
            // Get period info - safely access nested array
            $periodInfo = $dashboardData['data']['period'] ?? [];
            
            // Ensure meter data structure is complete
            $meterData = $dashboardData['data'];
            if (!isset($meterData['water'])) {
                $meterData['water'] = ['enabled' => false, 'meter' => null, 'readings' => []];
            }
            if (!isset($meterData['electricity'])) {
                $meterData['electricity'] = ['enabled' => false, 'meter' => null, 'readings' => []];
            }
            if (!isset($meterData['account'])) {
                $meterData['account'] = ['name' => $account->account_name ?? ''];
            }
            
            \Log::info('Readings page - Successfully loaded', [
                'account_id' => $accountId,
                'water_enabled' => $meterData['water']['enabled'] ?? false,
                'electricity_enabled' => $meterData['electricity']['enabled'] ?? false,
            ]);
            
            return view('admin.user-accounts.readings', [
                'account' => $account,
                'meterData' => $meterData,
                'periodInfo' => $periodInfo,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Readings view - Account not found', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('user-accounts.manager')
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Account not found');
                
        } catch (\Exception $e) {
            \Log::error('Readings view error', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('user-accounts.manager')
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading readings: ' . $e->getMessage());
        }
    }

    /**
     * Show Laravel Accounts page (billing history and payments)
     */
    public function showAccounts($accountId)
    {
        try {
            $account = Account::with(['site.user', 'tariffTemplate'])->findOrFail($accountId);
            
            // Get billing history using the same logic as the API
            $billingController = new \App\Http\Controllers\Api\BillingController(
                app(\App\Repositories\BillingRepository::class),
                app(\App\Services\DashboardService::class)
            );
            
            // Temporarily set the authenticated user to the account's user for history retrieval
            $originalUser = \Illuminate\Support\Facades\Auth::user();
            \Illuminate\Support\Facades\Auth::loginUsingId($account->site->user_id);
            
            try {
                $historyResponse = $billingController->getBillingHistoryForUser();
                $historyData = json_decode($historyResponse->getContent(), true);
                $accountData = $historyData['data'] ?? [];
            } finally {
                // Restore original authenticated user
                if ($originalUser) {
                    \Illuminate\Support\Facades\Auth::loginUsingId($originalUser->id);
                }
            }
            
            return view('admin.user-accounts.accounts', [
                'account' => $account,
                'accountData' => $accountData,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Accounts view error', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('user-accounts.manager')
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading accounts: ' . $e->getMessage());
        }
    }

    /**
     * Show edit account form
     * 
     * NEW: Separated screen for editing account details
     */
    public function editAccount($accountId)
    {
        try {
            $account = Account::with(['tariffTemplate', 'site.user'])->findOrFail($accountId);
            
            return view('admin.user-accounts.edit', [
                'account' => $account,
                'tariff' => $account->tariffTemplate,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('user-accounts.manager')
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading account: ' . $e->getMessage());
        }
    }

    /**
     * Update account details (web form submission)
     * 
     * NEW: Separated endpoint for web form updates
     */
    public function updateAccountDetails(Request $request, $accountId)
    {
        // Reuse existing updateAccount logic but return redirect for web form
        $jsonResponse = $this->updateAccount($request, $accountId);
        $jsonData = json_decode($jsonResponse->getContent(), true);
        
        if ($jsonData['status'] == 200) {
            return redirect()->route('user-accounts.edit', $accountId)
                ->with('alert-class', 'alert-success')
                ->with('alert-message', $jsonData['message']);
        } else {
            return redirect()->route('user-accounts.edit', $accountId)
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', $jsonData['message']);
        }
    }

    /**
     * Show manage meters screen
     * 
     * NEW: Separated screen for managing meters
     */
    public function manageMeters($accountId)
    {
        try {
            $account = Account::with(['meters.meterTypes', 'tariffTemplate'])->findOrFail($accountId);
            
            return view('admin.user-accounts.meters', [
                'account' => $account,
                'meters' => $account->meters,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('user-accounts.manager')
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading meters: ' . $e->getMessage());
        }
    }

    /**
     * Show add reading form
     * 
     * NEW: Separated screen for adding readings with billing calculation
     */
    public function addReadingForm($accountId)
    {
        try {
            $account = Account::with(['meters.meterTypes', 'tariffTemplate'])->findOrFail($accountId);
            $tariff = $account->tariffTemplate;
            
            if (!$tariff) {
                return redirect()->route('user-accounts.dashboard', $accountId)
                    ->with('alert-class', 'alert-danger')
                    ->with('alert-message', 'Account has no tariff template assigned. Please assign a tariff template first.');
            }
            
            // Get current period info based on billing mode
            $isDateToDate = $tariff->isDateToDateBilling();
            $periodInfo = null;
            
            if ($isDateToDate) {
                // Use DateToDatePeriodCalculator for DATE_TO_DATE mode
                $periodCalculator = app(DateToDatePeriodCalculator::class);
                $currentPeriod = $periodCalculator->getCurrentPeriod($account, $account->meters);
                $periodInfo = $currentPeriod ? [
                    'billing_type' => 'DATE_TO_DATE',
                    'period_number' => $currentPeriod['period_number'] ?? 1,
                    'start_date' => $currentPeriod['start_date'],
                    'end_date' => $currentPeriod['end_date'],
                    'status' => $currentPeriod['status'] ?? 'OPEN',
                    'days' => $currentPeriod['days'] ?? 0,
                ] : null;
            } else {
                // Use BillingPeriodCalculator for MONTHLY mode
                $billingController = new \App\Http\Controllers\Api\BillingController(
                    app(\App\Repositories\BillingRepository::class),
                    app(\App\Services\DashboardService::class)
                );
                
                // Call public getPeriodInfo method
                $periodInfo = $billingController->getPeriodInfo($account, $tariff);
            }
            
            return view('admin.user-accounts.readings-add', [
                'account' => $account,
                'meters' => $account->meters,
                'tariff' => $tariff,
                'periodInfo' => $periodInfo,
                'isDateToDate' => $isDateToDate,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading add reading form', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('user-accounts.dashboard', $accountId)
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading add reading form: ' . $e->getMessage());
        }
    }

    /**
     * Store reading from web form (triggers billing calculation)
     * 
     * NEW: Web form endpoint that uses ReadingEntryService
     */
    public function storeReading(Request $request, $accountId)
    {
        $request->validate([
            'meter_id' => 'required|exists:meters,id',
            'reading_date' => 'required|date',
            'reading_value' => 'required|string',
        ]);

        try {
            $meter = Meter::findOrFail($request->meter_id);
            
            // Verify meter belongs to account
            if ($meter->account_id != $accountId) {
                return redirect()->route('user-accounts.readings.add', $accountId)
                    ->with('alert-class', 'alert-danger')
                    ->with('alert-message', 'Invalid meter for this account.');
            }
            
            // Use ReadingEntryService to add reading and trigger billing
            $readingEntryService = app(ReadingEntryService::class);
            $result = $readingEntryService->addReading($meter, $request->reading_date, $request->reading_value);
            
            return redirect()->route('user-accounts.dashboard', $accountId)
                ->with('alert-class', 'alert-success')
                ->with('alert-message', 'Reading added successfully and billing calculated.');
                
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('user-accounts.readings.add', $accountId)
                ->withInput()
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Error storing reading via web form', [
                'account_id' => $accountId,
                'meter_id' => $request->meter_id,
                'date' => $request->reading_date,
                'value' => $request->reading_value,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('user-accounts.readings.add', $accountId)
                ->withInput()
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error adding reading: ' . $e->getMessage());
        }
    }

    /**
     * Show bills/history screen
     * 
     * NEW: Separated screen for viewing billing history with explicit periods
     */
    public function viewBills($accountId)
    {
        try {
            $account = Account::with(['tariffTemplate', 'site.user', 'meters'])->findOrFail($accountId);
            $tariff = $account->tariffTemplate;
            
            if (!$tariff) {
                return redirect()->route('user-accounts.dashboard', $accountId)
                    ->with('alert-class', 'alert-danger')
                    ->with('alert-message', 'Account has no tariff template assigned.');
            }
            
            $isDateToDate = $tariff->isDateToDateBilling();
            $bills = [];
            
            if ($isDateToDate) {
                // For DATE_TO_DATE: Use DateToDatePeriodCalculator to get explicit periods
                $periodCalculator = app(DateToDatePeriodCalculator::class);
                $periods = $periodCalculator->calculatePeriods($account, $account->meters);
                
                // Get bills from database and match them to periods
                $dbBills = \App\Models\Bill::where('account_id', $account->id)
                    ->with(['meter.meterTypes', 'openingReading', 'closingReading'])
                    ->orderBy('period_start_date', 'desc')
                    ->get();
                
                // Combine periods with bills
                foreach ($periods as $periodIndex => $period) {
                    $periodNumber = $period['period_number'] ?? ($periodIndex + 1);
                    
                    // Find matching bill for this period
                    $matchingBill = null;
                    foreach ($dbBills as $bill) {
                        if ($bill->period_start_date && $bill->period_end_date) {
                            $billStart = \Carbon\Carbon::parse($bill->period_start_date)->format('Y-m-d');
                            $billEnd = \Carbon\Carbon::parse($bill->period_end_date)->format('Y-m-d');
                            
                            if ($billStart === $period['start_date'] && 
                                ($period['end_date'] ? $billEnd === $period['end_date'] : true)) {
                                $matchingBill = $bill;
                                break;
                            }
                        }
                    }
                    
                    // Format bill data with explicit period number
                    $billData = [
                        'period_number' => $periodNumber,
                        'start_date' => $period['start_date'],
                        'end_date' => $period['end_date'],
                        'status' => $period['status'] ?? 'OPEN',
                        'consumption' => $period['total_usage'] ?? 0,
                        'usage_status' => $matchingBill ? ($matchingBill->usage_status ?? 'PROVISIONAL') : 'PROVISIONAL',
                        'tiered_charge' => $matchingBill ? (float) $matchingBill->tiered_charge : 0,
                        'fixed_costs_total' => $matchingBill ? (float) $matchingBill->fixed_costs_total : 0,
                        'vat_amount' => $matchingBill ? (float) $matchingBill->vat_amount : 0,
                        'total_amount' => $matchingBill ? (float) $matchingBill->total_amount : 0,
                        'vat_rate' => $tariff->getVatRate() ?? 15,
                        'billing_mode' => 'DATE_TO_DATE',
                    ];
                    
                    if ($matchingBill) {
                        $billData['id'] = $matchingBill->id;
                        $billData['created_at'] = $matchingBill->created_at ? $matchingBill->created_at->format('Y-m-d H:i:s') : null;
                    }
                    
                    $bills[] = $billData;
                }
            } else {
                // For MONTHLY: Use existing getBillingHistoryForUser logic
                $billingController = new \App\Http\Controllers\Api\BillingController(
                    app(\App\Repositories\BillingRepository::class),
                    app(\App\Services\DashboardService::class)
                );
                
                // Temporarily set the authenticated user to the account's user
                $originalUser = \Illuminate\Support\Facades\Auth::user();
                \Illuminate\Support\Facades\Auth::loginUsingId($account->site->user_id);
                
                try {
                    $historyResponse = $billingController->getBillingHistoryForUser();
                    $historyData = json_decode($historyResponse->getContent(), true);
                    $periodsData = $historyData['data']['periods'] ?? [];
                    
                    // Format bills with explicit period numbers for MONTHLY
                    $periodNumber = 1;
                    foreach ($periodsData as $periodData) {
                        $billData = [
                            'period_number' => $periodNumber++,
                            'start_date' => $periodData['start'] ?? $periodData['start_date'] ?? null,
                            'end_date' => $periodData['end'] ?? $periodData['end_date'] ?? null,
                            'consumption' => $periodData['consumption'] ?? 0,
                            'usage_status' => $periodData['usage_status'] ?? 'PROVISIONAL',
                            'tiered_charge' => $periodData['tiered_charge'] ?? $periodData['usage_charge'] ?? 0,
                            'fixed_costs_total' => $periodData['fixed_costs_total'] ?? 0,
                            'vat_amount' => $periodData['vat_amount'] ?? 0,
                            'total_amount' => $periodData['total_amount'] ?? $periodData['grand_total'] ?? 0,
                            'vat_rate' => $periodData['vat_rate'] ?? $tariff->getVatRate() ?? 15,
                            'billing_mode' => 'MONTHLY',
                        ];
                        
                        $bills[] = $billData;
                    }
                } finally {
                    // Restore original authenticated user
                    if ($originalUser) {
                        \Illuminate\Support\Facades\Auth::loginUsingId($originalUser->id);
                    }
                }
            }
            
            return view('admin.user-accounts.bills', [
                'account' => $account,
                'accountData' => ['bills' => $bills],
                'isDateToDate' => $isDateToDate,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading bills view', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('user-accounts.dashboard', $accountId)
                ->with('alert-class', 'alert-danger')
                ->with('alert-message', 'Error loading bills: ' . $e->getMessage());
        }
    }
}
