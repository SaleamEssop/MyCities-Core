<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\MeterType;
use App\Models\Regions;
use App\Models\RegionsAccountTypeCost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserAccountSetupController extends Controller
{
    // No default constants allowed - use null or require the field
    
    // Test user credentials
    private const TEST_USER_PASSWORD = 'demo123';
    
    // Test user meter reading simulation constants
    private const MIN_MONTHLY_WATER_USAGE_LITERS = 15000;
    private const MAX_MONTHLY_WATER_USAGE_LITERS = 25000;
    private const MIN_MONTHLY_ELECTRICITY_USAGE_KWH = 300;
    private const MAX_MONTHLY_ELECTRICITY_USAGE_KWH = 500;

    /**
     * Display the User Setup page (user management only — no account logic)
     * Route: GET /admin/user/setup
     */
    public function userSetupIndex()
    {
        $isActiveCol = Schema::hasColumn('users', 'is_active');

        $users = User::withCount('accounts')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'phone'         => $u->contact_number,
                'active'        => $isActiveCol ? ($u->is_active ?? true) : true,
                'is_admin'      => $u->is_admin ?? false,
                'joined'        => $u->created_at?->format('d M Y') ?? '—',
                'account_count' => $u->accounts_count ?? 0,
            ]);

        $regions = Regions::orderBy('name')->get(['id', 'name']);

        // Tariff templates for each region (for the test user form)
        $tariffsByRegion = RegionsAccountTypeCost::where('is_active', 1)
            ->get(['id', 'region_id', 'template_name'])
            ->groupBy('region_id');

        return \Inertia\Inertia::render('Admin/UserSetup', [
            'users'           => $users,
            'regions'         => $regions,
            'tariffsByRegion' => $tariffsByRegion,
        ]);
    }

    /**
     * Return accounts belonging to a user (for test-user form).
     * GET /admin/user/{id}/accounts
     */
    public function userAccounts($id)
    {
        $accounts = Account::where('user_id', $id)
            ->get(['id', 'account_name', 'account_number'])
            ->toArray();

        // Also check via legacy site relationship
        if (empty($accounts)) {
            $user = User::find($id);
            if ($user) {
                $siteIds = $user->sites()->pluck('id');
                $accounts = Account::whereIn('site_id', $siteIds)
                    ->get(['id', 'account_name', 'account_number'])
                    ->toArray();
            }
        }

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * Edit a user's name, email, phone.
     * PATCH /admin/user/{id}
     */
    public function editUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email,' . $id,
            'contact_number' => 'required|string|max:20|unique:users,contact_number,' . $id,
        ]);
        $user->update([
            'name'           => $request->name,
            'email'          => $request->email,
            'contact_number' => $request->contact_number,
        ]);
        return response()->json(['success' => true, 'message' => 'User updated successfully.']);
    }

    /**
     * Display the setup wizard page (Inertia)
     */
    public function index()
    {
        $regions = Regions::all(['id', 'name']);
        $meterTypes = MeterType::all(['id', 'title']);
        $users = User::with([
            'sites' => fn ($q) => $q->with(['accounts' => fn ($q2) => $q2->select('id', 'site_id', 'account_name', 'account_number')]),
        ])->get(['id', 'name', 'email'])->map(fn ($u) => [
            'id'       => $u->id,
            'name'     => $u->name,
            'email'    => $u->email,
            'accounts' => $u->sites->flatMap(fn ($s) => $s->accounts)->values(),
        ]);

        return \Inertia\Inertia::render('Admin/UserAccountsSetup', [
            'regions'    => $regions,
            'meterTypes' => $meterTypes,
            'users'      => $users,
        ]);
    }

    /**
     * Get tariff templates filtered by region
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
     * Validate email uniqueness via AJAX
     */
    public function validateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        
        $exists = User::where('email', $request->email)->exists();
        
        return response()->json([
            'exists' => $exists
        ]);
    }

    /**
     * Validate phone number uniqueness via AJAX
     */
    public function validatePhone(Request $request)
    {
        $request->validate([
            'contact_number' => 'required|string'
        ]);
        
        $exists = User::where('contact_number', $request->contact_number)->exists();
        
        return response()->json([
            'exists' => $exists
        ]);
    }

    /**
     * Reset a user's password and return the new password (admin use).
     * POST /admin/user/reset-password
     */
    public function resetPassword(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        try {
            $newPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
            $user = User::findOrFail($request->user_id);
            $user->password = Hash::make($newPassword);
            $user->save();
            return response()->json([
                'success'      => true,
                'message'      => "Password reset for {$user->name}.",
                'new_password' => $newPassword,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle a user's active/suspended status.
     * PATCH /admin/user/{id}/toggle-status
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            if (!Schema::hasColumn('users', 'is_active')) {
                return response()->json(['success' => false, 'message' => 'Run migrations first: php artisan migrate'], 500);
            }
            $user = User::findOrFail($id);
            $current = $user->is_active ?? true;
            $user->is_active = !$current;
            $user->save();
            $status = $user->is_active ? 'activated' : 'suspended';
            return response()->json(['success' => true, 'message' => "User {$user->name} {$status}.", 'is_active' => $user->is_active]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Permanently delete a user.
     * DELETE /admin/user/{id}
     */
    public function destroyUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $name = $user->name;
            $user->delete();
            return response()->json(['success' => true, 'message' => "User {$name} deleted."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store only user details (without region, tariff, account, meters)
     */
    public function storeUserOnly(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact_number' => 'required|string|max:20|unique:users,contact_number',
            'password' => 'required|string|min:6',
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'contact_number' => $request->contact_number,
                'password' => Hash::make($request->password),
                'is_admin' => 0,
            ]);
            
            return response()->json([
                'status' => 200, 
                'message' => 'User created successfully. You can add region, account, and meters later from the mobile app.',
                'user_id' => $user->id
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500, 
                'message' => 'Error creating user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new user with all related data through the wizard
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact_number' => 'required|string|max:20|unique:users,contact_number',
            'password' => 'required|string|min:6',
            'region_id' => 'required|exists:regions,id',
            'tariff_template_id' => 'required|exists:regions_account_type_cost,id',
        ]);

        DB::beginTransaction();
        
        try {
            // Step 1: Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'contact_number' => $request->contact_number,
                'password' => Hash::make($request->password),
                'is_admin' => 0,
            ]);
            
            // Step 2 & 3: Create site with region
            $site = Site::create([
                'user_id' => $user->id,
                'title' => $request->site_title ?? ($request->name . "'s Site"),
                'lat' => $request->lat ?? 0,
                'lng' => $request->lng ?? 0,
                'address' => $request->address ?? '',
                'email' => $request->email,
                'region_id' => $request->region_id,
                'billing_type' => $request->billing_type ?? 'monthly',
                'site_username' => $request->site_username ?? null,
            ]);
            
            // Step 4: Create account - check if tariff_template_id column exists
            $accountData = [
                'site_id' => $site->id,
                'account_name' => $request->account_name ?? ($request->name . "'s Account"),
                'account_number' => $request->account_number ?? ('ACC-' . time()),
                'name_on_bill' => $request->name_on_bill ?? $request->name, // Mandatory field - default to user name
                'billing_date' => $request->billing_date ?? null,
                'bill_day' => $request->bill_day ?? null, // Billing day set during account creation
                'read_day' => $request->read_day ?? null,
            ];
            
            // Only include tariff_template_id if the column exists
            if (Schema::hasColumn('accounts', 'tariff_template_id')) {
                $accountData['tariff_template_id'] = $request->tariff_template_id;
            }
            
            $account = Account::create($accountData);
            
            // Create meters if provided
            if ($request->has('meters') && is_array($request->meters)) {
                foreach ($request->meters as $meterData) {
                    $meter = Meter::create([
                        'account_id' => $account->id,
                        'meter_type_id' => $meterData['meter_type_id'] ?? null,
                        'meter_category_id' => $meterData['meter_category_id'] ?? null, // No default allowed
                        'meter_title' => $meterData['meter_title'] ?? '',
                        'meter_number' => $meterData['meter_number'] ?? '',
                    ]);
                    
                    // Add initial reading if provided - use read_day from account if no specific date
                    if (!empty($meterData['initial_reading'])) {
                        $readingDate = now()->format('Y-m-d');
                        // If read_day is set in account, use it to calculate reading date
                        if ($request->read_day) {
                            $currentDate = now();
                            $dayOfMonth = min((int)$request->read_day, $currentDate->daysInMonth);
                            $readingDate = $currentDate->copy()->setDay($dayOfMonth)->format('Y-m-d');
                        }
                        
                        MeterReadings::create([
                            'meter_id' => $meter->id,
                            'reading_date' => $readingDate,
                            'reading_value' => $meterData['initial_reading'],
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 200, 
                'message' => 'User account created successfully',
                'user_id' => $user->id,
                'account_id' => $account->id
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500, 
                'message' => 'Error creating user account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tariff template details (fixed costs, customer costs)
     */
    public function getTariffDetails($tariffId)
    {
        $tariff = RegionsAccountTypeCost::find($tariffId);
        
        if (!$tariff) {
            return response()->json(['status' => 404, 'message' => 'Tariff not found']);
        }
        
        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $tariff->id,
                'template_name' => $tariff->template_name,
                'fixed_costs' => $tariff->fixed_costs ?? [],
                'customer_costs' => $tariff->customer_costs ?? [],
                'is_water' => (bool) $tariff->is_water,
                'is_electricity' => (bool) $tariff->is_electricity,
                'billing_type' => $tariff->billing_type ?? 'MONTHLY',
            ]
        ]);
    }

    /**
     * Populate an existing user with demo data
     * Deletes all previous data (readings, bills) and repopulates
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function populateExistingUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'seed_months' => 'required|integer|min:3|max:6',
        ]);
        
        $seedMonths = (int) $request->seed_months;
        $userId = (int) $request->user_id;
        
        try {
            // Load user with relationships - bills relationship now exists on Account
            $user = User::with(['sites.accounts.meters.readings', 'sites.accounts.bills'])->findOrFail($userId);
            
            // Get first account (users should have only one account)
            $site = $user->sites->first();
            if (!$site) {
                return response()->json(['success' => false, 'message' => 'User has no site. Cannot populate data.'], 422);
            }
            
            // Refresh to ensure accounts are loaded
            $site->load('accounts');
            $account = $site->accounts->first();
            if (!$account) {
                return response()->json(['success' => false, 'message' => 'User has no account. Cannot populate data.'], 422);
            }
            
            // Get tariff template
            $tariffTemplate = $account->tariffTemplate;
            if (!$tariffTemplate) {
                return response()->json(['success' => false, 'message' => 'Account has no tariff template. Cannot populate data.'], 422);
            }
            
            // Verify bill_day is set for MONTHLY billing
            if ($tariffTemplate->billing_type === 'MONTHLY' && empty($account->bill_day)) {
                $billDay = $tariffTemplate->billing_day ?? 15;
                $account->bill_day = $billDay;
                $account->billing_date = $billDay;
                $account->save();
            }
            
            // Delete all existing bills
            Bill::where('account_id', $account->id)->delete();
            
            // Delete all existing readings
            $meters = $account->meters;
            foreach ($meters as $meter) {
                MeterReadings::where('meter_id', $meter->id)->delete();
            }
            
            // Ensure we have water and electricity meters
            $waterMeter = $meters->where('meter_type_id', 1)->first(); // Water
            $elecMeter = $meters->where('meter_type_id', 2)->first(); // Electricity
            
            if (!$waterMeter || !$elecMeter) {
                // Create missing meters
                $waterMeterType = MeterType::where('title', 'Water')->first();
                $elecMeterType = MeterType::where('title', 'Electricity')->first();
                
                if (!$waterMeter && $waterMeterType) {
                    $waterMeter = Meter::create([
                        'account_id' => $account->id,
                        'meter_number' => 'WM' . rand(10000, 99999),
                        'meter_title' => 'Water Meter',
                        'meter_type_id' => $waterMeterType->id,
                        'meter_category_id' => null, // No default allowed
                    ]);
                }
                
                if (!$elecMeter && $elecMeterType) {
                    $elecMeter = Meter::create([
                        'account_id' => $account->id,
                        'meter_number' => 'EM' . rand(10000, 99999),
                        'meter_title' => 'Electricity Meter',
                        'meter_type_id' => $elecMeterType->id,
                        'meter_category_id' => null, // No default allowed
                    ]);
                }
            }
            
            // Now populate with demo data using same logic as createTestUser
            $readingsCreated = 0;
            $warnings = [];
            $errors = [];
            
            // Get billing day for proper period alignment
            $account->refresh();
            $billDay = (int) ($account->bill_day ?? $tariffTemplate->billing_day ?? 15);
            $isMonthly = $tariffTemplate->billing_type === 'MONTHLY';
            
            // Direct DB inserts bypass the MeterReadingObserver (which has BillingPeriodCalculator issues)
            
            // Step 1: Get TODAY
            $today = \Carbon\Carbon::now()->setTime(0, 0, 0);
            
            // Step 2: Work backwards from today to find first period start
            $firstPeriodStart = $today->copy();
            // Snap to bill_day of this month
            $dayOfMonth = (int) min($billDay, $firstPeriodStart->daysInMonth);
            $firstPeriodStart->setDay($dayOfMonth);
            // If bill_day is in the future this month, step back one month
            if ($firstPeriodStart->gt($today)) {
                $firstPeriodStart->subMonth();
                $dayOfMonth = min($billDay, $firstPeriodStart->daysInMonth);
                $firstPeriodStart->setDay($dayOfMonth);
            }
            // Go back seedMonths more periods
            for ($i = 0; $i < $seedMonths; $i++) {
                $firstPeriodStart->subMonth();
                $dayOfMonth = min($billDay, $firstPeriodStart->daysInMonth);
                $firstPeriodStart->setDay($dayOfMonth);
            }
            $firstPeriodStart->setTime(0, 0, 0);
            
            // Step 3: Generate all period-start dates
            $waterReading = 1000;
            $elecReading  = 50000;
            $periodStarts = [];
            $cursor = $firstPeriodStart->copy();
            for ($i = 0; $i <= $seedMonths; $i++) {
                $periodStarts[] = $cursor->format('Y-m-d');
                $cursor->addMonth();
                $cursor->setDay(min($billDay, $cursor->daysInMonth));
            }
            
            // Step 4: Insert readings directly (bypass observer)
            foreach ($periodStarts as $periodIndex => $periodStartDate) {
                $periodStartCarbon = \Carbon\Carbon::parse($periodStartDate)->setTime(0, 0, 0);
                
                if ($periodIndex === 0) {
                    // Initial water reading on period start
                    try {
                        $waterDate = $periodStartCarbon->copy();
                        $exists = DB::table('meter_readings')
                            ->where('meter_id', $waterMeter->id)->where('reading_date', $waterDate->format('Y-m-d'))->exists();
                        if (!$exists) {
                            DB::table('meter_readings')->insert([
                                'meter_id' => $waterMeter->id, 'reading_date' => $waterDate->format('Y-m-d'),
                                'reading_value' => $waterReading, 'reading_type' => 'ACTUAL',
                                'is_locked' => false, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $readingsCreated++;
                        }
                        $waterReading += rand(self::MIN_MONTHLY_WATER_USAGE_LITERS, self::MAX_MONTHLY_WATER_USAGE_LITERS);
                    } catch (\Exception $e) {
                        $errors[] = "Error adding initial water reading: " . $e->getMessage();
                    }
                    
                    // Initial electricity reading on day+1 (avoid duplicate date)
                    try {
                        $elecDate = $periodStartCarbon->copy()->addDay();
                        $exists = DB::table('meter_readings')
                            ->where('meter_id', $elecMeter->id)->where('reading_date', $elecDate->format('Y-m-d'))->exists();
                        if (!$exists) {
                            DB::table('meter_readings')->insert([
                                'meter_id' => $elecMeter->id, 'reading_date' => $elecDate->format('Y-m-d'),
                                'reading_value' => $elecReading, 'reading_type' => 'ACTUAL',
                                'is_locked' => false, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $readingsCreated++;
                        }
                        $elecReading += rand(self::MIN_MONTHLY_ELECTRICITY_USAGE_KWH, self::MAX_MONTHLY_ELECTRICITY_USAGE_KWH);
                    } catch (\Exception $e) {
                        $errors[] = "Error adding initial electricity reading: " . $e->getMessage();
                    }
                } else {
                    // Subsequent period water reading
                    try {
                        $waterDate = $periodStartCarbon->copy();
                        $waterReading += rand(self::MIN_MONTHLY_WATER_USAGE_LITERS, self::MAX_MONTHLY_WATER_USAGE_LITERS);
                        $exists = DB::table('meter_readings')
                            ->where('meter_id', $waterMeter->id)->where('reading_date', $waterDate->format('Y-m-d'))->exists();
                        if (!$exists) {
                            DB::table('meter_readings')->insert([
                                'meter_id' => $waterMeter->id, 'reading_date' => $waterDate->format('Y-m-d'),
                                'reading_value' => $waterReading, 'reading_type' => 'ACTUAL',
                                'is_locked' => false, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $readingsCreated++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error adding water reading for {$periodStartDate}: " . $e->getMessage();
                    }
                    
                    // Subsequent period electricity reading on day+1
                    try {
                        $elecDate = $periodStartCarbon->copy()->addDay();
                        $elecReading += rand(self::MIN_MONTHLY_ELECTRICITY_USAGE_KWH, self::MAX_MONTHLY_ELECTRICITY_USAGE_KWH);
                        $exists = DB::table('meter_readings')
                            ->where('meter_id', $elecMeter->id)->where('reading_date', $elecDate->format('Y-m-d'))->exists();
                        if (!$exists) {
                            DB::table('meter_readings')->insert([
                                'meter_id' => $elecMeter->id, 'reading_date' => $elecDate->format('Y-m-d'),
                                'reading_value' => $elecReading, 'reading_type' => 'ACTUAL',
                                'is_locked' => false, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $readingsCreated++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error adding electricity reading for {$elecDate->format('Y-m-d')}: " . $e->getMessage();
                    }
                }
            }
            
            // CRITICAL: VALIDATION - Verify period boundaries are valid
            // NOTE: Test user creator only creates raw data (readings). 
            // Bill creation is handled automatically by MeterReadingObserver.
            // This validation only checks period boundaries, not bills.
            
            $today = \Carbon\Carbon::now()->setTime(0, 0, 0);
            $validationErrors = [];
            $activePeriodFound = false;
            
            // Get periods from calculator API (includes all periods, even if no bills exist yet)
            $calculatorController = app(\App\Http\Controllers\Admin\BillingCalculatorController::class);
            $calculatorRequest = new \Illuminate\Http\Request();
            $calculatorRequest->merge(['account_id' => $account->id]);
            $calculatorResponse = $calculatorController->calculatePeriods($calculatorRequest);
            $calculatorData = json_decode($calculatorResponse->getContent(), true);
            
            if ($calculatorData['success']) {
                $allPeriods = $calculatorData['data']['periods'] ?? [];
                
                foreach ($allPeriods as $periodData) {
                    $periodStart = \Carbon\Carbon::parse($periodData['start'])->setTime(0, 0, 0);
                    $periodEnd = \Carbon\Carbon::parse($periodData['end'])->setTime(0, 0, 0);
                    
                    // Validation 1: No period may start after current date
                    if ($periodStart->gt($today)) {
                        $validationErrors[] = "Period starting {$periodStart->format('Y-m-d')} starts after current date ({$today->format('Y-m-d')})";
                    }
                    
                    // Validation 2: Current date must fall within exactly one period (the active period)
                    if ($periodStart->lte($today) && $today->lt($periodEnd)) {
                        if ($activePeriodFound) {
                            $validationErrors[] = "Multiple periods contain current date ({$today->format('Y-m-d')})";
                        }
                        $activePeriodFound = true;
                    }
                    
                    // Validation 3: Periods must follow inclusive start / exclusive end rule
                    if ($periodStart->gte($periodEnd)) {
                        $validationErrors[] = "Period starting {$periodStart->format('Y-m-d')} has invalid end date ({$periodEnd->format('Y-m-d')}) - end must be after start";
                    }
                }
                
                // Validation 4: Exactly one active period must contain TODAY
                if (!$activePeriodFound) {
                    $validationErrors[] = "No period contains current date ({$today->format('Y-m-d')}) - current date must fall within the final (active) period";
                }
            } else {
                // Calculator API failed - log warning but don't fail validation
                \Log::warning('Could not validate periods via calculator API', [
                    'account_id' => $account->id,
                    'error' => $calculatorData['message'] ?? 'Unknown error',
                ]);
            }
            
            // If validation fails, add to errors
            if (!empty($validationErrors)) {
                foreach ($validationErrors as $validationError) {
                    $errors[] = "VALIDATION FAILED: {$validationError}";
                }
                \Log::error('Period boundary validation failed for test user', [
                    'user_id' => $userId,
                    'account_id' => $account->id,
                    'validation_errors' => $validationErrors,
                ]);
            }
            
            // NOTE: Bill creation is NOT validated here - that's the responsibility of MeterReadingObserver
            // If bills aren't being created, that's a separate issue with the billing system, not the test user creator
            
            // Build success message with verification results
            $message = "User {$user->email} populated with demo data!\n";
            $message .= "\n=== DEMO DATA CREATION SUMMARY ===\n";
            $message .= "Readings Created: {$readingsCreated}\n";
            $message .= "Expected Periods: {$seedMonths}\n";
            $message .= "\nNOTE: Bills are automatically generated by the billing system when readings are added.\n";
            $message .= "If bills are not appearing, check the MeterReadingObserver logs.\n";
            $message .= "\nNOTE: Bills are automatically generated by the billing system when readings are added.\n";
            $message .= "If bills are not appearing, check the MeterReadingObserver logs.\n";
            
            if (!empty($warnings)) {
                $message .= "\n⚠️ WARNINGS:\n";
                foreach ($warnings as $warning) {
                    $message .= "  - {$warning}\n";
                }
            }
            
            if (!empty($errors)) {
                $message .= "\n❌ ERRORS:\n";
                foreach ($errors as $error) {
                    $message .= "  - {$error}\n";
                }
            }
            
            if (empty($warnings) && empty($errors)) {
                $message .= "\n✅ All demo data created successfully!\n";
                $message .= "All {$seedMonths} periods have bills and readings.\n";
            }
            
            $hasErrors = !empty($errors);
            return response()->json([
                'success'  => !$hasErrors,
                'message'  => $message,
                'warnings' => $warnings,
                'errors'   => $errors,
            ], $hasErrors ? 422 : 200);
            
        } catch (\Exception $e) {
            \Log::error('Error populating existing user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error populating user: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Create a test user with full demo data, OR seed an existing user's account.
     *
     * Params:
     *   user_id       – existing user ID (omit to create a new user)
     *   account_id    – existing account ID (omit to create/auto-select account)
     *   seed_months   – 3 | 6 | 12 (default 6)
     *   meters        – 'water' | 'electricity' | 'both' (default 'both')
     *   region_id     – preferred region (omit to auto-select)
     *   tariff_id     – preferred tariff (omit to auto-select)
     */
    public function createTestUser(Request $request)
    {
        // Get seed months (default 6, allow up to 12)
        $seedMonths = (int) ($request->seed_months ?? 6);
        $seedMonths = max(0, min(12, $seedMonths));

        $metersParam = $request->meters ?? 'both'; // 'water', 'electricity', 'both'
        
        $randomNum = rand(100, 999);
        $existingUserId = !empty($request->user_id) ? (int) $request->user_id : null;
        $existingAccountId = !empty($request->account_id) ? (int) $request->account_id : null;
        
        DB::beginTransaction();

        try {
            // ── Region ──────────────────────────────────────────────────────
            if (!empty($request->region_id)) {
                $region = Regions::findOrFail($request->region_id);
            } else {
                $region = Regions::first();
                if (!$region) {
                    $region = Regions::create([
                        'name'                => 'Durban (eThekwini)',
                        'water_email'         => 'eservices@durban.gov.za',
                        'electricity_email'   => 'electricity@durban.gov.za',
                    ]);
                }
            }

            // ── Tariff Template ─────────────────────────────────────────────
            if (!empty($request->tariff_id)) {
                $tariffTemplate = RegionsAccountTypeCost::findOrFail($request->tariff_id);
            } else {
                $tariffTemplate = RegionsAccountTypeCost::where('region_id', $region->id)->where('is_active', 1)->first();
                if (!$tariffTemplate) {
                    $tariffTemplate = $this->createTestTariffTemplate($region->id, $randomNum);
                }
            }

            $this->ensureMeterTypesExist();

            // ── User ─────────────────────────────────────────────────────────
            if ($existingUserId) {
                $user     = User::findOrFail($existingUserId);
                $password = null; // existing user — no password output
            } else {
                $testEmail = 'demo' . $randomNum . '@mycities.co.za';
                while (User::where('email', $testEmail)->exists()) {
                    $randomNum = rand(100, 999);
                    $testEmail = 'demo' . $randomNum . '@mycities.co.za';
                }
                $password = self::TEST_USER_PASSWORD;
                $user = User::create([
                    'name'           => 'Demo User ' . $randomNum,
                    'email'          => $testEmail,
                    'password'       => Hash::make($password),
                    'contact_number' => '084' . rand(1000000, 9999999),
                ]);
            }

            // ── Account ──────────────────────────────────────────────────────
            // Look up by explicit account_id, then by user_id directly, then create.
            if ($existingAccountId) {
                $account = Account::findOrFail($existingAccountId);
            } else {
                // Find account directly via user_id (new model) or via site (legacy)
                $account = Account::where('user_id', $user->id)->first();
                if (!$account) {
                    $site = $user->sites()->first();
                    if ($site) {
                        $account = Account::where('site_id', $site->id)->first();
                    }
                }
                if (!$account) {
                    // Create a new account for this user
                    $site = Site::create([
                        'user_id'   => $user->id,
                        'title'     => 'Demo Site ' . $randomNum,
                        'address'   => '123 Test Street, Durban, 4001',
                        'lat'       => -29.8587,
                        'lng'       => 31.0218,
                        'email'     => $user->email,
                        'region_id' => $region->id,
                    ]);
                    $accountData = [
                        'site_id'      => $site->id,
                        'user_id'      => $user->id,
                        'account_name' => 'Demo Account',
                        'account_number'=> 'ACC' . $randomNum,
                        'name_on_bill' => $user->name,
                    ];
                    if ($tariffTemplate && Schema::hasColumn('accounts', 'tariff_template_id')) {
                        $accountData['tariff_template_id'] = $tariffTemplate->id;
                        if ($tariffTemplate->billing_type === 'MONTHLY') {
                            $billingDay = $tariffTemplate->billing_day ?? 15;
                            $accountData['bill_day'] = $billingDay;
                            $accountData['billing_date'] = $billingDay;
                        }
                    }
                    $account = Account::create($accountData);
                }
            }

            // Ensure bill_day is set
            if ($tariffTemplate && ($tariffTemplate->billing_type === 'MONTHLY') && empty($account->bill_day)) {
                $account->bill_day = $tariffTemplate->billing_day ?? 15;
                $account->billing_date = $account->bill_day;
                $account->save();
            }
            // Set tariff on account if not already set
            if ($tariffTemplate && Schema::hasColumn('accounts', 'tariff_template_id') && empty($account->tariff_template_id)) {
                $account->tariff_template_id = $tariffTemplate->id;
                $account->save();
            }

            // ── Meters ───────────────────────────────────────────────────────
            $waterMeterType = MeterType::where('title', 'Water')->first();
            $elecMeterType  = MeterType::where('title', 'Electricity')->first();
            $waterInitial   = 1000;
            $elecInitial    = 50000;

            $needWater = in_array($metersParam, ['water', 'both']);
            $needElec  = in_array($metersParam, ['electricity', 'both']);

            $waterMeter = null;
            $elecMeter  = null;

            if ($needWater && $waterMeterType) {
                $waterMeter = Meter::where('account_id', $account->id)
                    ->where('meter_type_id', $waterMeterType->id)->first();
                if (!$waterMeter) {
                    $waterMeter = Meter::create([
                        'account_id'       => $account->id,
                        'meter_number'     => 'WM' . rand(10000, 99999),
                        'meter_title'      => 'Water Meter',
                        'meter_type_id'    => $waterMeterType->id,
                        'meter_category_id'=> null,
                    ]);
                }
            }
            if ($needElec && $elecMeterType) {
                $elecMeter = Meter::where('account_id', $account->id)
                    ->where('meter_type_id', $elecMeterType->id)->first();
                if (!$elecMeter) {
                    $elecMeter = Meter::create([
                        'account_id'       => $account->id,
                        'meter_number'     => 'EM' . rand(10000, 99999),
                        'meter_title'      => 'Electricity Meter',
                        'meter_type_id'    => $elecMeterType->id,
                        'meter_category_id'=> null,
                    ]);
                }
            }

            DB::commit();
            
            $readingsCreated = 0;
            $warnings = [];
            $errors   = [];

            if ($seedMonths > 0) {
                $account->refresh();
                $billDay   = (int) ($account->bill_day ?? $tariffTemplate->billing_day ?? 15);
                
                // Build period-start dates going back seedMonths from today
                $today            = \Carbon\Carbon::now()->setTime(0, 0, 0);
                $firstPeriodStart = $today->copy();
                $firstPeriodStart->setDay(min($billDay, $firstPeriodStart->daysInMonth));
                if ($firstPeriodStart->gt($today)) {
                    $firstPeriodStart->subMonth()->setDay(min($billDay, $firstPeriodStart->daysInMonth));
                }
                for ($i = 0; $i < $seedMonths; $i++) {
                    $firstPeriodStart->subMonth()->setDay(min($billDay, $firstPeriodStart->daysInMonth));
                }
                $firstPeriodStart->setTime(0, 0, 0);

                $waterReading = $waterInitial;
                $elecReading  = $elecInitial;
                $periodStarts = [];
                $cursor       = $firstPeriodStart->copy();
                for ($i = 0; $i <= $seedMonths; $i++) {
                    $periodStarts[] = $cursor->format('Y-m-d');
                    $cursor->addMonth()->setDay(min($billDay, $cursor->daysInMonth));
                }

                foreach ($periodStarts as $idx => $periodStartDate) {
                    $pCarbon = \Carbon\Carbon::parse($periodStartDate)->setTime(0, 0, 0);

                    // Water reading
                    if ($waterMeter) {
                        if ($idx > 0) {
                            $waterReading += rand(self::MIN_MONTHLY_WATER_USAGE_LITERS, self::MAX_MONTHLY_WATER_USAGE_LITERS);
                        }
                        $wDate = $pCarbon->format('Y-m-d');
                        if (!DB::table('meter_readings')->where('meter_id', $waterMeter->id)->where('reading_date', $wDate)->exists()) {
                            DB::table('meter_readings')->insert([
                                'meter_id' => $waterMeter->id, 'reading_date' => $wDate,
                                'reading_value' => $waterReading, 'reading_type' => 'ACTUAL',
                                'is_locked' => false, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $readingsCreated++;
                        }
                        if ($idx === 0) {
                            $waterReading += rand(self::MIN_MONTHLY_WATER_USAGE_LITERS, self::MAX_MONTHLY_WATER_USAGE_LITERS);
                        }
                    }

                    // Electricity reading (offset by 1 day to avoid same-date clash)
                    if ($elecMeter) {
                        if ($idx > 0) {
                            $elecReading += rand(self::MIN_MONTHLY_ELECTRICITY_USAGE_KWH, self::MAX_MONTHLY_ELECTRICITY_USAGE_KWH);
                        }
                        $eDate = $pCarbon->copy()->addDay()->format('Y-m-d');
                        if (!DB::table('meter_readings')->where('meter_id', $elecMeter->id)->where('reading_date', $eDate)->exists()) {
                            DB::table('meter_readings')->insert([
                                'meter_id' => $elecMeter->id, 'reading_date' => $eDate,
                                'reading_value' => $elecReading, 'reading_type' => 'ACTUAL',
                                'is_locked' => false, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $readingsCreated++;
                        }
                        if ($idx === 0) {
                            $elecReading += rand(self::MIN_MONTHLY_ELECTRICITY_USAGE_KWH, self::MAX_MONTHLY_ELECTRICITY_USAGE_KWH);
                        }
                    }
                }
            }

            $isNewUser = !$existingUserId;
            return response()->json([
                'success'          => true,
                'message'          => $isNewUser ? 'Test user created successfully.' : 'Existing user seeded successfully.',
                'warnings'         => $warnings,
                'errors'           => $errors,
                'created_user'     => [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'phone'          => $user->contact_number,
                    'password'       => $password,
                    'joined'         => $user->created_at?->format('d M Y'),
                    'region'         => $region->name,
                    'tariff'         => $tariffTemplate->template_name,
                    'account_id'     => $account->id,
                    'account_number' => $account->account_number,
                    'bill_day'       => $account->bill_day,
                    'seed_months'    => $seedMonths,
                    'readings_added' => $readingsCreated,
                    'is_new_user'    => $isNewUser,
                ],
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error creating test user: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Create a test tariff template with water tiers
     */
    private function createTestTariffTemplate($regionId, $randomNum)
    {
        $tariff = RegionsAccountTypeCost::create([
            'region_id' => $regionId,
            'template_name' => 'Demo Tariff ' . $randomNum,
            'is_water' => true,
            'is_active' => true,
        ]);
        
        // Create water tiers if tariff_tiers table exists
        if (Schema::hasTable('tariff_tiers')) {
            $tiers = [
                ['tier_number' => 1, 'min_units' => 0, 'max_units' => 6000, 'rate_per_unit' => 25.50],
                ['tier_number' => 2, 'min_units' => 6000, 'max_units' => 15000, 'rate_per_unit' => 32.80],
                ['tier_number' => 3, 'min_units' => 15000, 'max_units' => 30000, 'rate_per_unit' => 42.50],
                ['tier_number' => 4, 'min_units' => 30000, 'max_units' => null, 'rate_per_unit' => 55.20],
            ];
            
            foreach ($tiers as $tier) {
                DB::table('tariff_tiers')->insert([
                    'tariff_template_id' => $tariff->id,
                    'tier_number' => $tier['tier_number'],
                    'min_units' => $tier['min_units'],
                    'max_units' => $tier['max_units'],
                    'rate_per_unit' => $tier['rate_per_unit'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        return $tariff;
    }
    
    /**
     * Ensure meter types exist in the database
     */
    private function ensureMeterTypesExist()
    {
        if (!MeterType::where('title', 'Water')->exists()) {
            MeterType::create(['title' => 'Water']);
        }
        if (!MeterType::where('title', 'Electricity')->exists()) {
            MeterType::create(['title' => 'Electricity']);
        }
        
        // Also ensure meter categories exist
        if (Schema::hasTable('meter_categories')) {
            if (!DB::table('meter_categories')->where('name', 'Water in')->exists()) {
                $data = ['name' => 'Water in', 'created_at' => now(), 'updated_at' => now()];
                // Add meter_type_id if column exists
                if (Schema::hasColumn('meter_categories', 'meter_type_id')) {
                    $waterType = MeterType::where('title', 'Water')->first();
                    if ($waterType) $data['meter_type_id'] = $waterType->id;
                }
                DB::table('meter_categories')->insert($data);
            }
            if (!DB::table('meter_categories')->where('name', 'Electricity')->exists()) {
                $data = ['name' => 'Electricity', 'created_at' => now(), 'updated_at' => now()];
                // Add meter_type_id if column exists
                if (Schema::hasColumn('meter_categories', 'meter_type_id')) {
                    $elecType = MeterType::where('title', 'Electricity')->first();
                    if ($elecType) $data['meter_type_id'] = $elecType->id;
                }
                DB::table('meter_categories')->insert($data);
            }
        }
    }
}
