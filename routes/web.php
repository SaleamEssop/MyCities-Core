<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\RegionsCostController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TariffTemplateController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UserAccountSetupController;
use App\Http\Controllers\Admin\UserAccountManagerController;
use App\Http\Controllers\Admin\DashboardErrorsController;
use App\Http\Controllers\Admin\AdministratorsController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\User\UserAuthController;
use App\Http\Controllers\User\UserAppController;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Site;
use App\Models\Account;
use App\Models\Regions;
use App\Models\Meter;
use App\Models\MeterReadings;
use App\Models\Payment;
use App\Models\TariffTemplate;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingPageController::class, 'show'])->name('landing');

// Inertia + Vue 3 (single-page entry for migration)
Route::get('/inertia-welcome', fn () => Inertia::render('Welcome'))->name('inertia.welcome');

// Public Inertia smoke-test — no auth, always accessible
Route::get('/inertia-test', fn () => Inertia::render('Billing/Calculator', [
    'message' => 'Inertia smoke-test (no auth). If you see this, Inertia is working.',
]))->name('inertia.test');

Route::get('/app', function () {
    return view('web_app_blade');
});

// ============================================================
// User App routes — /user/*
// ============================================================

// Public user routes (no auth required)
Route::get('/user', fn () => redirect()->route('user.info'));
Route::get('/user/splash',  [UserAppController::class, 'splash'])->name('user.splash');
Route::get('/user/login',   [UserAuthController::class, 'showLogin'])->name('user.login');
Route::post('/user/login',  [UserAuthController::class, 'login'])->name('user.login.submit');
Route::get('/user/info',    [UserAppController::class, 'infoPages'])->name('user.info');

// Authenticated user routes
Route::middleware('auth')->prefix('user')->name('user.')->group(function () {
    Route::get('/logout',              [UserAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard',           [UserAppController::class,  'dashboard'])->name('dashboard');
    Route::get('/reading/water',       [UserAppController::class,  'waterReading'])->name('reading.water');
    Route::get('/reading/electricity', [UserAppController::class,  'electricityReading'])->name('reading.electricity');
    Route::get('/reading/history',     [UserAppController::class,  'readingHistory'])->name('reading.history');
    Route::get('/account',             [UserAppController::class,  'account'])->name('account');

    // JSON API endpoints
    Route::post('/api/reading',  [UserAppController::class, 'storeReading'])->name('api.reading.store');
    Route::get('/api/dashboard', [UserAppController::class, 'dashboardData'])->name('api.dashboard');
    Route::get('/api/accounts',  [UserAppController::class, 'accountsList'])->name('api.accounts');
});

Route::get('/admin/login', fn () => Inertia::render('Admin/Login'))->name('login');

Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');

// Admin Forgot Password
Route::get('/admin/forgot-password', fn () => Inertia::render('Admin/ForgotPassword'))->name('admin.forgot-password');

Route::post('/admin/forgot-password', [AdminController::class, 'forgotPassword'])->name('admin.forgot-password.submit');


// Admin routes
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/', fn () => Inertia::render('Admin/Home'))->name('admin.home');

    Route::get('/logout', function () {
        Auth::logout();
        return redirect('/admin/login');
    })->name('admin.logout');

    // --- SYSTEM INTELLIGENCE ---
    Route::get('/system-intelligence', [\App\Http\Controllers\SystemIntelligenceController::class, 'index'])
        ->name('system.intelligence');
    Route::post('/system-intelligence/new-session', [\App\Http\Controllers\SystemIntelligenceController::class, 'newSession'])
        ->name('system.intelligence.new-session');
    Route::post('/system-intelligence/clear-all', [\App\Http\Controllers\SystemIntelligenceController::class, 'clearAll'])
        ->name('system.intelligence.clear-all');
    Route::get('/system-intelligence/session/{sessionId}/logs', [\App\Http\Controllers\SystemIntelligenceController::class, 'getSessionLogs'])
        ->name('system.intelligence.session-logs');
    Route::get('/system-intelligence/check-updates', [\App\Http\Controllers\SystemIntelligenceController::class, 'checkForUpdates'])
        ->name('system.intelligence.check-updates');

    // --- USER SETUP (User management only — no account/meter logic) ---
    Route::get('user/setup', [UserAccountSetupController::class, 'userSetupIndex'])->name('user.setup');
    Route::post('user/create', [UserAccountSetupController::class, 'storeUserOnly'])->name('user.create');
    Route::get('user/{id}/accounts', [UserAccountSetupController::class, 'userAccounts'])->name('user.accounts');
    Route::patch('user/{id}', [UserAccountSetupController::class, 'editUser'])->name('user.edit');
    Route::post('user/reset-password', [UserAccountSetupController::class, 'resetPassword'])->name('user.reset-password');
    Route::patch('user/{id}/toggle-status', [UserAccountSetupController::class, 'toggleStatus'])->name('user.toggle-status');
    Route::delete('user/{id}', [UserAccountSetupController::class, 'destroyUser'])->name('user.destroy');

    // --- USER ACCOUNTS - SETUP (legacy redirect) ---
    Route::get('user-accounts/setup', fn () => redirect()->route('user.setup'))->name('user-accounts.setup');
    Route::post('user-accounts/setup', [UserAccountSetupController::class, 'store'])->name('user-accounts.setup.store');
    Route::post('user-accounts/setup/user-only', [UserAccountSetupController::class, 'storeUserOnly'])->name('user-accounts.setup.store-user-only');
    Route::post('user-accounts/setup/validate-email', [UserAccountSetupController::class, 'validateEmail'])->name('user-accounts.setup.validate-email');
    Route::post('user-accounts/setup/validate-phone', [UserAccountSetupController::class, 'validatePhone'])->name('user-accounts.setup.validate-phone');
    Route::get('user-accounts/setup/tariffs/{regionId}', [UserAccountSetupController::class, 'getTariffTemplatesByRegion'])->name('user-accounts.setup.tariffs');
    Route::get('user-accounts/setup/tariff-details/{tariffId}', [UserAccountSetupController::class, 'getTariffDetails'])->name('user-accounts.setup.tariff-details');
    Route::post('user-accounts/setup/create-test-user', [UserAccountSetupController::class, 'createTestUser'])->name('user-accounts.setup.create-test-user');
    Route::post('user-accounts/setup/populate-existing-user', [UserAccountSetupController::class, 'populateExistingUser'])->name('user-accounts.setup.populate-existing-user');

    // --- USER ACCOUNTS - MANAGER (Dashboard) ---
    Route::get('user-accounts/manager', function () {
        $users = \App\Models\User::withCount('sites')
            ->with(['sites.accounts' => function ($q) {
                $q->select('id', 'site_id', 'account_name')->limit(1);
            }])
            ->get()
            ->map(function ($user) {
                $firstAccountId = null;
                foreach ($user->sites as $site) {
                    if ($site->accounts && $site->accounts->count() > 0) {
                        $firstAccountId = $site->accounts->first()->id;
                        break;
                    }
                }
                return [
                    'id'               => $user->id,
                    'name'             => $user->name,
                    'email'            => $user->email,
                    'phone'            => $user->contact_number,
                    'first_account_id' => $firstAccountId,
                    'sites_count'      => $user->sites_count,
                ];
            });
        return Inertia::render('Admin/UserAccountsManager', ['users' => $users]);
    })->name('user-accounts.manager');
    Route::get('user-accounts/manager/search', [UserAccountManagerController::class, 'search'])->name('user-accounts.manager.search');
    Route::get('user-accounts/manager/user/{id}', [UserAccountManagerController::class, 'getUserData'])->name('user-accounts.manager.user');
    Route::put('user-accounts/manager/user/{id}', [UserAccountManagerController::class, 'updateUser'])->name('user-accounts.manager.update-user');
    Route::delete('user-accounts/manager/user/{id}', [UserAccountManagerController::class, 'deleteUser'])->name('user-accounts.manager.delete-user');
    Route::put('user-accounts/manager/account/{id}', [UserAccountManagerController::class, 'updateAccount'])->name('user-accounts.manager.update-account');
    Route::post('user-accounts/manager/meter', [UserAccountManagerController::class, 'addMeter'])->name('user-accounts.manager.add-meter');
    Route::put('user-accounts/manager/meter/{id}', [UserAccountManagerController::class, 'updateMeter'])->name('user-accounts.manager.update-meter');
    Route::delete('user-accounts/manager/meter/{id}', [UserAccountManagerController::class, 'deleteMeter'])->name('user-accounts.manager.delete-meter');
    Route::post('user-accounts/manager/reading', [UserAccountManagerController::class, 'addReading'])->name('user-accounts.manager.add-reading');
    Route::get('user-accounts/manager/readings/{meterId}', [UserAccountManagerController::class, 'getReadings'])->name('user-accounts.manager.readings');
    Route::get('user-accounts/manager/tariffs/{regionId}', [UserAccountManagerController::class, 'getTariffTemplatesByRegion'])->name('user-accounts.manager.tariffs');
    // Account billing & payments
    Route::get('user-accounts/manager/billing/{accountId}', [UserAccountManagerController::class, 'getAccountBilling'])->name('user-accounts.manager.billing');
    Route::get('user-accounts/manager/billing-history/{accountId}', [UserAccountManagerController::class, 'getBillingHistory'])->name('user-accounts.manager.billing-history');
    Route::get('user-accounts/billing/{accountId}', [UserAccountManagerController::class, 'showAccountBilling'])->name('user-accounts.billing');
    Route::get('user-accounts/manager/webApp/{accountId}', [UserAccountManagerController::class, 'showWebApp'])->name('user-accounts.manager.webapp');
    Route::get('user-accounts/dashboard/{accountId}', [UserAccountManagerController::class, 'showDashboard'])->name('user-accounts.dashboard');
    Route::get('user-accounts/dashboard-errors/{accountId}', [DashboardErrorsController::class, 'showErrors'])->name('dashboard-errors.show');
    Route::post('user-accounts/dashboard-errors/{accountId}/clear', [DashboardErrorsController::class, 'clearErrors'])->name('dashboard-errors.clear');
    Route::get('user-accounts/readings/{accountId}', [UserAccountManagerController::class, 'showReadings'])->name('user-accounts.readings');
    Route::get('user-accounts/accounts/{accountId}', [UserAccountManagerController::class, 'showAccounts'])->name('user-accounts.accounts');

    // --- NEW SEPARATED SCREENS (Following Dashboard Design) ---
    Route::get('user-accounts/{accountId}/edit', [UserAccountManagerController::class, 'editAccount'])->name('user-accounts.edit');
    Route::put('user-accounts/{accountId}/update', [UserAccountManagerController::class, 'updateAccountDetails'])->name('user-accounts.update');
    Route::get('user-accounts/{accountId}/meters', [UserAccountManagerController::class, 'manageMeters'])->name('user-accounts.meters');
    Route::get('user-accounts/{accountId}/readings/add', [UserAccountManagerController::class, 'addReadingForm'])->name('user-accounts.readings.add');
    Route::post('user-accounts/{accountId}/readings/store', [UserAccountManagerController::class, 'storeReading'])->name('user-accounts.readings.store');
    Route::get('user-accounts/{accountId}/bills', [UserAccountManagerController::class, 'viewBills'])->name('user-accounts.bills');

    // Simplified route - takes userId and finds first account automatically
    Route::get('user-webapp/{userId}', [UserAccountManagerController::class, 'showUserWebApp'])->name('user-webapp');

    // App View - webapp work screen
    Route::get('app-view', [\App\Http\Controllers\Admin\AppPreviewController::class, 'index'])->name('app-view');
    // Switch session to a user account so admin can view the app as that user
    Route::get('app-view/switch-user/{userId}', [\App\Http\Controllers\Admin\AppPreviewController::class, 'switchUser'])->name('app-view.switch-user');
    // Restore admin session
    Route::get('app-view/restore-admin', [\App\Http\Controllers\Admin\AppPreviewController::class, 'restoreAdmin'])->name('app-view.restore-admin');

    // --- MONITORING (Boundary-Level Observability) ---
    Route::prefix('monitoring')->group(function () {
        Route::post('start', [\App\Http\Controllers\Admin\MonitoringController::class, 'start'])->name('monitoring.start');
        Route::post('stop/{sessionId}', [\App\Http\Controllers\Admin\MonitoringController::class, 'stop'])->name('monitoring.stop');
        Route::get('events/{sessionId}', [\App\Http\Controllers\Admin\MonitoringController::class, 'getEvents'])->name('monitoring.events');
        Route::post('events/{sessionId}', [\App\Http\Controllers\Admin\MonitoringController::class, 'addEvent'])->name('monitoring.add-event');
        Route::delete('session/{sessionId}', [\App\Http\Controllers\Admin\MonitoringController::class, 'clear'])->name('monitoring.clear');
        Route::get('sessions', [\App\Http\Controllers\Admin\MonitoringController::class, 'getActiveSessions'])->name('monitoring.sessions');
    });
    Route::post('user-accounts/manager/payment', [UserAccountManagerController::class, 'addPayment'])->name('user-accounts.manager.add-payment');
    Route::delete('user-accounts/manager/payment/{id}', [UserAccountManagerController::class, 'deletePayment'])->name('user-accounts.manager.delete-payment');

    // --- ENHANCED USER MANAGEMENT (replaces legacy user routes for main listing) ---
    Route::get('user-management', [UserManagementController::class, 'index'])->name('user-management.index');
    Route::get('user-management/search', [UserManagementController::class, 'search'])->name('user-management.search');
    Route::post('user-management', [UserManagementController::class, 'store'])->name('user-management.store');
    Route::get('user-management/{id}', [UserManagementController::class, 'getUserData'])->name('user-management.show');
    Route::put('user-management/{id}', [UserManagementController::class, 'update'])->name('user-management.update');
    Route::delete('user-management/{id}', [UserManagementController::class, 'destroy'])->name('user-management.destroy');
    Route::post('user-management/generate-test', [UserManagementController::class, 'generateTestUser'])->name('user-management.generate-test');
    Route::delete('user-management/delete-test', [UserManagementController::class, 'deleteTestUsers'])->name('user-management.delete-test');
    Route::post('user-management/clone/{id}', [UserManagementController::class, 'cloneUser'])->name('user-management.clone');

    // --- USERS (Legacy routes - kept for backward compatibility) ---
    Route::get('users', function () {
        $users = User::orderBy('name')->get()->map(fn ($u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'phone' => $u->contact_number,
            'site'  => null, // users own many sites; show individually via user-accounts
        ]);
        return Inertia::render('Admin/Users', ['users' => $users]);
    })->name('show-users');
    Route::get('users/add', [AdminController::class, 'addUserForm'])->name('add-user-form');
    Route::post('users/add', [AdminController::class, 'createUser'])->name('add-user');
    Route::get('users/edit/{id}', [AdminController::class, 'editUserForm']);
    Route::post('users/edit', [AdminController::class, 'editUser'])->name('edit-user');
    Route::get('users/delete/{id}', [AdminController::class, 'deleteUser']);

    // --- SITES ---
    Route::get('sites', function () {
        $sites = Site::with('region')->orderBy('title')->get()->map(fn ($s) => [
            'id'      => $s->id,
            'name'    => $s->title,       // DB column is 'title'; Vue expects 'name'
            'address' => $s->address,
            'region'  => $s->region ? ['name' => $s->region->name] : null,
        ]);
        return Inertia::render('Admin/Sites', ['sites' => $sites]);
    })->name('show-sites');
    Route::get('sites/add', [AdminController::class, 'addSiteForm'])->name('create-site-form');
    Route::post('sites/add', [AdminController::class, 'createSite'])->name('add-site');
    Route::get('sites/edit/{id}', [AdminController::class, 'editSiteForm'])->name('edit-site-form');
    Route::get('site/edit/{id}', [AdminController::class, 'editSiteForm'])->name('edit-site-form-alt'); // Alias for backward compatibility
    Route::post('sites/edit', [AdminController::class, 'editSite'])->name('edit-site');
    Route::get('sites/delete/{id}', [AdminController::class, 'deleteSite']);
    Route::post('sites/get-by-user', [AdminController::class, 'getSitesByUser'])->name('get-sites-by-user');

    // --- ACCOUNT MANAGER (new clean wizard: user search → account → meters) ---
    Route::get('account-manager', [\App\Http\Controllers\Admin\AccountManagerController::class, 'index'])->name('account-manager');
    Route::get('account-manager/user/{id}', [\App\Http\Controllers\Admin\AccountManagerController::class, 'getUserAccounts'])->name('account-manager.user');
    Route::post('account-manager/account', [\App\Http\Controllers\Admin\AccountManagerController::class, 'storeAccount'])->name('account-manager.account.store');
    Route::post('account-manager/meter', [\App\Http\Controllers\Admin\AccountManagerController::class, 'storeMeter'])->name('account-manager.meter.store');
    Route::delete('account-manager/meter/{id}', [\App\Http\Controllers\Admin\AccountManagerController::class, 'deleteMeter'])->name('account-manager.meter.delete');

    // --- ACCOUNTS ---
    Route::get('accounts', function () {
        $accounts = Account::with('site')->orderBy('id')->get()->map(fn ($a) => [
            'id'             => $a->id,
            'account_number' => $a->account_number,
            'name_on_bill'   => $a->name_on_bill ?? $a->account_name,
            'email'          => $a->water_email ?? $a->electricity_email,
            'site'           => $a->site ? ['name' => $a->site->title] : null,
        ]);
        return Inertia::render('Admin/Accounts', ['accounts' => $accounts]);
    })->name('account-list');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts/store', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/add', [AdminController::class, 'addAccountForm'])->name('add-account-form');
    Route::post('accounts/add', [AdminController::class, 'createAccount'])->name('add-account');
    Route::get('account/edit/{id}', [AdminController::class, 'editAccountForm']);
    Route::post('account/edit', [AdminController::class, 'editAccount'])->name('edit-account');
    Route::get('account/delete/{id}', [AdminController::class, 'deleteAccount']);

    // AJAX Routes for Dropdowns & Details
    Route::post('accounts/get-by-site', [AdminController::class, 'getAccountsBySite'])->name('get-accounts-by-site');
    Route::post('accounts/get-details', [AdminController::class, 'getAccountDetails'])->name('get-account-details');

    // --- METERS ---
    Route::get('meters', function () {
        $meters = Meter::with(['meterTypes', 'account.site'])->orderBy('id')->get()->map(fn ($m) => [
            'id'           => $m->id,
            'meter_number' => $m->meter_number,
            'meterType'    => $m->meterTypes ? ['name' => $m->meterTypes->name] : null,
            'site'         => $m->account?->site ? ['name' => $m->account->site->title] : null,
            'status'       => $m->status ?? 'Active',
        ]);
        return Inertia::render('Admin/Meters', ['meters' => $meters]);
    })->name('meters-list');
    Route::get('meters/add', [AdminController::class, 'addMeterForm'])->name('add-meter-form');
    Route::post('meters/add', [AdminController::class, 'createMeter'])->name('add-meter');

    // --- READINGS ---
    Route::get('readings', function () {
        $readings = MeterReadings::with('meter')->latest()->limit(500)->get();
        return Inertia::render('Admin/Readings', ['readings' => $readings]);
    })->name('meter-reading-list');
    Route::get('readings/add', [AdminController::class, 'addReadingForm'])->name('add-meter-reading-form');
    Route::post('readings/add', [AdminController::class, 'createReading'])->name('add-reading');

    // --- PAYMENTS ---
    Route::get('payments', function () {
        $payments = Payment::with('account')->latest()->limit(500)->get();
        return Inertia::render('Admin/Payments', ['payments' => $payments]);
    })->name('payments-list');
    Route::get('payments/add', [AdminController::class, 'addPaymentForm'])->name('add-payment-form');
    Route::post('payments/add', [AdminController::class, 'createPayment'])->name('add-payment');
    Route::get('payments/delete/{id}', [AdminController::class, 'deletePayment']);

    // --- REGION COSTS (Legacy routes - kept for backward compatibility) ---
    Route::get('region_cost', [RegionsCostController::class, 'index'])->name('region-cost');
    Route::get('region_cost/create', [RegionsCostController::class, 'create'])->name('region-cost-create');
    Route::post('region_cost', [RegionsCostController::class, 'store'])->name('region-cost-store');
    Route::get('/region_cost/edit/{id}', [RegionsCostController::class, 'edit'])->name('region-cost-edit');
    Route::post('region_cost/update', [RegionsCostController::class, 'update'])->name('update-region-cost');
    Route::get('/region_cost/delete/{id}', [RegionsCostController::class, 'delete']);
    Route::post('region_cost/copy_record', [RegionsCostController::class, 'copyRecord'])->name('copy-region-cost');

    // --- TARIFF TEMPLATES (New routes) ---
    Route::get('tariff_template', function () {
        $templates = TariffTemplate::with('region')->orderBy('template_name')->get()->map(fn ($t) => [
            'id'             => $t->id,
            'name'           => $t->template_name,   // DB column is 'template_name'; Vue expects 'name'
            'region'         => $t->region ? ['name' => $t->region->name] : null,
            'is_water'       => $t->is_water,
            'is_electricity' => $t->is_electricity,
            'effective_from' => $t->effective_from,
            'is_active'      => $t->is_active,
        ]);
        return Inertia::render('Admin/TariffTemplates', ['templates' => $templates]);
    })->name('tariff-template');
    Route::get('tariff_template/create', [TariffTemplateController::class, 'create'])->name('tariff-template-create');
    Route::post('tariff_template', [TariffTemplateController::class, 'store'])->name('tariff-template-store');
    Route::get('/tariff_template/edit/{id}', [TariffTemplateController::class, 'edit'])->name('tariff-template-edit');
    Route::post('tariff_template/update', [TariffTemplateController::class, 'update'])->name('update-tariff-template');
    Route::get('/tariff_template/delete/{id}', [TariffTemplateController::class, 'delete']);
    Route::post('tariff_template/copy_record', [TariffTemplateController::class, 'copyRecord'])->name('copy-tariff-template');

    // --- TARIFF TEMPLATES BY REGION (AJAX endpoint) ---
    Route::get('tariff-templates/by-region/{regionId}', [AdminController::class, 'getTariffTemplatesByRegion'])->name('get-tariff-templates-by-region');

    // --- CALCULATOR (PD.md ↔ Calculator.php → Calculator.vue via Inertia) ---
    Route::get('calculator', [\App\Http\Controllers\Admin\CalculatorController::class, 'index'])->name('calculator');
    Route::post('calculator/compute', [\App\Http\Controllers\Admin\CalculatorController::class, 'compute'])->name('calculator.compute');
    Route::post('calculator/compute-charge', [\App\Http\Controllers\Admin\CalculatorController::class, 'computeCharge'])->name('calculator.compute-charge');
    Route::get('calculator/meter/{id}', [\App\Http\Controllers\Admin\CalculatorController::class, 'getMeterData'])->name('calculator.meter-data');
    Route::get('calculator/account/{id}', [\App\Http\Controllers\Admin\CalculatorController::class, 'getAccountData'])->name('calculator.account-data');
    Route::post('calculator/reading', [\App\Http\Controllers\Admin\CalculatorController::class, 'addReading'])->name('calculator.add-reading');
    Route::delete('calculator/reading/{id}', [\App\Http\Controllers\Admin\CalculatorController::class, 'deleteReading'])->name('calculator.delete-reading');
    Route::post('calculator/calculate-periods', [\App\Http\Controllers\Admin\CalculatorController::class, 'calculatePeriods'])->name('calculator.calculate-periods');

    // --- REGIONS ---
    Route::get('regions', function () {
        $regions = Regions::with('zones')->orderBy('name')->get()->map(fn ($r) => [
            'id'                => $r->id,
            'name'              => $r->name,
            'province'          => $r->province,
            'municipality'      => $r->municipality,
            'water_email'       => $r->water_email,
            'electricity_email' => $r->electricity_email,
            'zones_count'       => $r->zones->count(),
        ]);
        return Inertia::render('Admin/Regions', ['regions' => $regions]);
    })->name('regions-list');
    Route::post('/region/edit', [AdminController::class, 'editRegion'])->name('edit-region');
    Route::get('region/add', [AdminController::class, 'addRegionForm'])->name('add-region-form');
    Route::get('/region/edit/{id}', [AdminController::class, 'editRegionForm'])->name('edit-region-form');
    Route::post('regions/add', [AdminController::class, 'createRegion'])->name('add-region');
    Route::get('/region/delete/{id}', [AdminController::class, 'deleteRegion']);
    Route::get('/region/email/{id}', [AdminController::class, 'getEmailBasedRegion'])->name('get-email-region');
    Route::get('/region/zones/{regionId}', [AdminController::class, 'getRegionZones'])->name('region.zones');

    // --- ALARMS ---
    Route::get('alarms', function () {
        $alarms = \App\Models\AlarmDefinition::orderBy('code')->get();
        return Inertia::render('Admin/Alarms', ['alarms' => $alarms]);
    })->name('alarms');

    // --- ADS & CONTENT MANAGEMENT ---
    Route::get('ads', fn () => Inertia::render('Admin/Ads'))->name('ads-list');
    Route::post('ads/add', [AdsController::class, 'store'])->name('add-ads');
    Route::get('ads/edit/{id}', [AdsController::class, 'edit'])->name('edit-ads-form');
    Route::post('ads/edit', [AdsController::class, 'update'])->name('edit-ad');
    Route::get('ads/delete/{id}', [AdsController::class, 'destroy'])->name('delete-ad');
    Route::get('ads/landing-settings', [AdsController::class, 'landingSettings'])->name('ads.landing-settings');
    Route::post('ads/landing-settings', [AdsController::class, 'saveLandingSettings'])->name('ads.landing-settings.save');

    // --- ADS CATEGORIES ---
    Route::get('ads-categories', [AdsController::class, 'categories'])->name('ads-categories');
    Route::post('ads-category/add', [AdsController::class, 'storeCategory'])->name('add-ads-category');
    Route::post('ads-category/edit', [AdsController::class, 'updateCategory'])->name('edit-ads-category');
    Route::get('ads-category/delete/{id}', [AdsController::class, 'destroyCategory'])->name('delete-ads-category');

    // --- CKEDITOR IMAGE UPLOAD (legacy) ---
    Route::post('ckeditor/upload', [AdsController::class, 'uploadImage'])->name('ckeditor.image-upload');

    // --- EDITOR.JS IMAGE UPLOAD ---
    Route::post('editor/image-upload',    [\App\Http\Controllers\EditorImageController::class, 'upload'])->name('editor.image.upload');
    Route::post('editor/image-by-url',    [\App\Http\Controllers\EditorImageController::class, 'uploadByUrl'])->name('editor.image.by-url');

    // --- PAGE MANAGEMENT ---
    Route::get('pages', [PagesController::class, 'index'])->name('pages-list');
    Route::get('pages/create', [PagesController::class, 'create'])->name('pages-create');
    Route::post('pages/store', [PagesController::class, 'store'])->name('pages-store');
    Route::get('pages/edit/{id}', [PagesController::class, 'edit'])->name('pages-edit');
    Route::post('pages/update', [PagesController::class, 'update'])->name('pages-update');
    Route::get('pages/delete/{id}', [PagesController::class, 'destroy'])->name('pages-delete');
    Route::get('pages/preview/{id}', [PagesController::class, 'preview'])->name('pages-preview');
    Route::post('pages/toggle-active/{id}', [PagesController::class, 'toggleActive'])->name('pages-toggle-active');
    Route::post('pages/update-order', [PagesController::class, 'updateOrder'])->name('pages-update-order');

    // --- ADDRESS & ZONE PROXIES (server-side to avoid CORS / User-Agent restrictions) ---
    Route::get('api/address-suggest', [AdminController::class, 'addressSuggest'])->name('address.suggest');
    Route::get('api/zone-lookup',     [AdminController::class, 'zoneLookup'])->name('zone.lookup');

    // --- APPLICATION SETTINGS ---
    Route::get('settings', fn () => Inertia::render('Admin/Settings'))->name('settings.index');
    Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');

    // --- ADMINISTRATORS ---
    Route::get('administrators', [AdministratorsController::class, 'index'])->name('administrators.index');
    Route::post('administrators', [AdministratorsController::class, 'store'])->name('administrators.store');
    Route::get('administrators/{id}', [AdministratorsController::class, 'getAdministrator'])->name('administrators.show');
    Route::put('administrators/{id}', [AdministratorsController::class, 'update'])->name('administrators.update');
    Route::delete('administrators/{id}', [AdministratorsController::class, 'destroy'])->name('administrators.destroy');
});
