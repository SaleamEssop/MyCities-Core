# MyCities User App — Complete Implementation Specification

**Purpose:** This document is a complete, self-contained build guide for the MyCities
user-facing web app. Follow it top-to-bottom. Do not deviate without recording the reason.

**Compliance check:** After building, a senior AI will verify against this spec.

---

## 1. Stack & Constraints

| Item | Value |
|---|---|
| Framework | Laravel 11, PHP 8.3 |
| Frontend | Vue 3 (`<script setup>`), Inertia.js v1.x, Vite |
| Entry point | `resources/js/inertia-app.js` (already exists, do NOT modify) |
| Blade root | `resources/views/app.blade.php` (already exists, do NOT modify) |
| Route helper | `route()` from `ziggy-js` (already installed via `ZiggyVue` plugin) |
| Admin panel | lives at `/admin/*` — do NOT touch those files except `AdminLayout.vue` |
| Auth guard | Standard Laravel `auth` guard (`users` table) |
| Calculator | `App\Services\Billing\Calculator` — single entry point `computePeriod(int $billId): array` |

**NEVER use `window.route()`.** Always `import { route } from 'ziggy-js'` OR rely on
the globally registered `route()` from `ZiggyVue`.

---

## 2. Color Palette & CSS Variables

Define these once in `resources/css/user-app.css` (create this file).
Reference it from `app.blade.php` — add one `<link>` tag after the existing CSS lines:

```html
<link href="/css/user-app.css" rel="stylesheet">
```

**`resources/css/user-app.css` content:**

```css
/* MyCities User App — Design Tokens */
:root {
  --ua-primary:        #009BA4;   /* teal — header, buttons, accents */
  --ua-primary-dark:   #007A82;   /* hover / pressed state */
  --ua-orange:         #FF9800;   /* alert banners, "reading due" pill */
  --ua-orange-dark:    #E65100;   /* orange hover */
  --ua-amount:         #1565C0;   /* grand total R amounts */
  --ua-water:          #2196F3;   /* water section icon + accent */
  --ua-electricity:    #FFA000;   /* electricity section icon + accent */
  --ua-green:          #4CAF50;   /* payments, credits, Actual status */
  --ua-grey:           #9E9E9E;   /* Estimated status */
  --ua-amber:          #FF8F00;   /* Provisional status */
  --ua-bg:             #F5F5F5;   /* page background */
  --ua-card:           #FFFFFF;   /* card / section background */
  --ua-text:           #212121;   /* primary body text */
  --ua-text-secondary: #757575;   /* secondary / label text */
  --ua-divider:        #E0E0E0;   /* dividers, borders */
  --ua-shadow:         0 2px 4px rgba(0,0,0,0.10);
  --ua-radius:         8px;
  --ua-radius-sm:      4px;
}

/* Google Font: Nunito */
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700&display=swap');

body { font-family: 'Nunito', sans-serif; }
```

---

## 3. Complete File Manifest

### Files to CREATE (new)

```
app/Http/Controllers/User/
  UserAuthController.php
  UserAppController.php

resources/js/Layouts/
  UserAppLayout.vue

resources/js/Components/
  NumericKeypad.vue
  MeterDisplay.vue

resources/js/Pages/UserApp/
  Splash.vue
  Login.vue
  Dashboard.vue
  WaterReading.vue
  ElectricityReading.vue
  ReadingHistory.vue
  AccountStatement.vue

resources/css/
  user-app.css
```

### Files to MODIFY (existing)

```
routes/web.php                           — add /user/* route group
resources/js/Layouts/AdminLayout.vue     — add "App View" nav item
resources/views/app.blade.php            — add user-app.css link
```

---

## 4. Routes — Add to `routes/web.php`

Add the following block **before** the `Route::get('/', ...)` line at the top of the
admin route definitions. Insert the two new `use` statements with the existing ones at
the top of the file.

**New `use` statements** (add after existing ones around line 27):
```php
use App\Http\Controllers\User\UserAuthController;
use App\Http\Controllers\User\UserAppController;
```

**New route group** (add after the existing `Route::get('/app', ...)` line):

```php
// ============================================================
// User App routes — /user/*
// ============================================================

// Public user routes (no auth)
Route::get('/user', function () {
    return redirect()->route('user.splash');
});
Route::get('/user/splash',     [UserAppController::class, 'splash'])->name('user.splash');
Route::get('/user/login',      [UserAuthController::class, 'showLogin'])->name('user.login');
Route::post('/user/login',     [UserAuthController::class, 'login'])->name('user.login.submit');

// Authenticated user routes
Route::middleware('auth')->prefix('user')->name('user.')->group(function () {
    Route::get('/logout',                    [UserAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard',                 [UserAppController::class, 'dashboard'])->name('dashboard');
    Route::get('/reading/water',             [UserAppController::class, 'waterReading'])->name('reading.water');
    Route::get('/reading/electricity',       [UserAppController::class, 'electricityReading'])->name('reading.electricity');
    Route::get('/reading/history',           [UserAppController::class, 'readingHistory'])->name('reading.history');
    Route::get('/account',                   [UserAppController::class, 'account'])->name('account');

    // JSON API endpoints (AJAX, return JSON)
    Route::post('/api/reading',              [UserAppController::class, 'storeReading'])->name('api.reading.store');
    Route::get('/api/dashboard',             [UserAppController::class, 'dashboardData'])->name('api.dashboard');
    Route::get('/api/accounts',              [UserAppController::class, 'accountsList'])->name('api.accounts');
});
```

---

## 5. `UserAuthController.php`

**Path:** `app/Http/Controllers/User/UserAuthController.php`

```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('user.dashboard');
        }
        return Inertia::render('UserApp/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('user.dashboard'));
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login');
    }
}
```

---

## 6. `UserAppController.php`

**Path:** `app/Http/Controllers/User/UserAppController.php`

```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Billing\Calculator;
use App\Services\Billing\Calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserAppController extends Controller
{
    // ------------------------------------------------------------------
    // SPLASH — public, date-matched ad
    // ------------------------------------------------------------------
    public function splash()
    {
        $today = now('Africa/Johannesburg')->format('Y-m-d');

        $ad = DB::table('ads')
            ->where('is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->orderBy('created_at', 'desc')
            ->first();

        $nextUrl = Auth::check()
            ? route('user.dashboard')
            : route('user.login');

        return Inertia::render('UserApp/Splash', [
            'ad'      => $ad ? [
                'id'      => $ad->id,
                'title'   => $ad->title,
                'content' => $ad->content,
                'image'   => $ad->image ?? null,
            ] : null,
            'nextUrl' => $nextUrl,
        ]);
    }

    // ------------------------------------------------------------------
    // DASHBOARD
    // ------------------------------------------------------------------
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        // Load all accounts for this user (with meters eager-loaded)
        $accounts = DB::table('accounts')
            ->where('user_id', $user->id)
            ->get();

        if ($accounts->isEmpty()) {
            return Inertia::render('UserApp/Dashboard', [
                'accounts'           => [],
                'currentAccount'     => null,
                'waterBill'          => null,
                'electricityBill'    => null,
                'readingDueInDays'   => null,
                'periodLabel'        => null,
                'today'              => now('Africa/Johannesburg')->format('d M Y'),
            ]);
        }

        // Active account: from session or first
        $accountId = $request->session()->get('user_account_id', $accounts->first()->id);
        $account   = $accounts->firstWhere('id', $accountId) ?? $accounts->first();

        // Period index for navigation (0 = current, negative = past)
        $periodIndex = (int) $request->get('period', 0);

        // Load meters for this account
        $meters = DB::table('meters')->where('account_id', $account->id)->get();

        $waterBill       = null;
        $electricityBill = null;
        $readingDueInDays = null;

        foreach ($meters as $meter) {
            $billData = $this->getBillDataForMeter($meter, $periodIndex);
            if ($meter->type === 'water') {
                $waterBill = $billData;
                if ($billData) {
                    $readingDueInDays = $this->calcReadingDueDays($meter->id, $billData['period_end_date']);
                }
            } elseif ($meter->type === 'electricity') {
                $electricityBill = $billData;
                if (!$readingDueInDays && $billData) {
                    $readingDueInDays = $this->calcReadingDueDays($meter->id, $billData['period_end_date']);
                }
            }
        }

        $periodLabel = $waterBill
            ? (date('d M Y', strtotime($waterBill['period_start_date'])) . ' to ' . date('d M Y', strtotime($waterBill['period_end_date'])))
            : null;

        return Inertia::render('UserApp/Dashboard', [
            'accounts'           => $accounts->map(fn($a) => ['id' => $a->id, 'account_number' => $a->account_number])->values(),
            'currentAccount'     => ['id' => $account->id, 'account_number' => $account->account_number],
            'waterBill'          => $waterBill,
            'electricityBill'    => $electricityBill,
            'readingDueInDays'   => $readingDueInDays,
            'periodLabel'        => $periodLabel,
            'periodIndex'        => $periodIndex,
            'today'              => now('Africa/Johannesburg')->format('d M Y'),
        ]);
    }

    // ------------------------------------------------------------------
    // WATER READING PAGE
    // ------------------------------------------------------------------
    public function waterReading(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $meter = DB::table('meters')
            ->where('account_id', $account->id)
            ->where('type', 'water')
            ->first();

        if (!$meter) {
            return redirect()->route('user.dashboard');
        }

        $readings = DB::table('meter_readings')
            ->where('meter_id', $meter->id)
            ->orderBy('reading_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'reading_date' => date('d M Y', strtotime($r->reading_date)),
                'reading_value'=> $this->formatWaterReading($r->reading_value),
                'reading_type' => $r->reading_type ?? 'Actual',
            ])->values();

        $latestBill = DB::table('bills')
            ->where('meter_id', $meter->id)
            ->orderBy('period_end_date', 'desc')
            ->first();

        return Inertia::render('UserApp/WaterReading', [
            'meter'       => [
                'id'            => $meter->id,
                'meter_number'  => $meter->meter_number ?? ('M' . str_pad($meter->id, 6, '0', STR_PAD_LEFT)),
                'start_reading' => $this->formatWaterReading($meter->start_reading ?? 0),
                'start_date'    => $meter->start_reading_date
                    ? date('d M Y', strtotime($meter->start_reading_date))
                    : null,
            ],
            'readings'    => $readings,
            'periodLabel' => $latestBill
                ? date('d M Y', strtotime($latestBill->period_start_date)) . ' to ' . date('d M Y', strtotime($latestBill->period_end_date))
                : null,
        ]);
    }

    // ------------------------------------------------------------------
    // ELECTRICITY READING PAGE
    // ------------------------------------------------------------------
    public function electricityReading(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $meter = DB::table('meters')
            ->where('account_id', $account->id)
            ->where('type', 'electricity')
            ->first();

        if (!$meter) {
            return redirect()->route('user.dashboard');
        }

        $readings = DB::table('meter_readings')
            ->where('meter_id', $meter->id)
            ->orderBy('reading_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'reading_date'  => date('d M Y', strtotime($r->reading_date)),
                'reading_value' => $this->formatElectricityReading($r->reading_value),
                'reading_type'  => $r->reading_type ?? 'Actual',
            ])->values();

        $latestBill = DB::table('bills')
            ->where('meter_id', $meter->id)
            ->orderBy('period_end_date', 'desc')
            ->first();

        return Inertia::render('UserApp/ElectricityReading', [
            'meter'       => [
                'id'           => $meter->id,
                'meter_number' => $meter->meter_number ?? ('E' . str_pad($meter->id, 6, '0', STR_PAD_LEFT)),
                'start_reading'=> $this->formatElectricityReading($meter->start_reading ?? 0),
                'start_date'   => $meter->start_reading_date
                    ? date('d M Y', strtotime($meter->start_reading_date))
                    : null,
            ],
            'readings'    => $readings,
            'periodLabel' => $latestBill
                ? date('d M Y', strtotime($latestBill->period_start_date)) . ' to ' . date('d M Y', strtotime($latestBill->period_end_date))
                : null,
        ]);
    }

    // ------------------------------------------------------------------
    // READING HISTORY
    // ------------------------------------------------------------------
    public function readingHistory(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $meters = DB::table('meters')->where('account_id', $account->id)->get();

        $allReadings = [];
        foreach ($meters as $meter) {
            $readings = DB::table('meter_readings')
                ->where('meter_id', $meter->id)
                ->orderBy('reading_date', 'desc')
                ->get()
                ->map(fn($r) => [
                    'id'            => $r->id,
                    'meter_type'    => $meter->type,
                    'reading_date'  => date('d M Y', strtotime($r->reading_date)),
                    'reading_value' => $meter->type === 'water'
                        ? $this->formatWaterReading($r->reading_value)
                        : $this->formatElectricityReading($r->reading_value),
                    'reading_type'  => $r->reading_type ?? 'Actual',
                ])->values()->all();
            $allReadings = array_merge($allReadings, $readings);
        }

        usort($allReadings, fn($a, $b) => strcmp($b['reading_date'], $a['reading_date']));

        return Inertia::render('UserApp/ReadingHistory', [
            'readings' => array_values($allReadings),
        ]);
    }

    // ------------------------------------------------------------------
    // ACCOUNT STATEMENT
    // ------------------------------------------------------------------
    public function account(Request $request)
    {
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);

        if (!$account) {
            return redirect()->route('user.dashboard');
        }

        $bills = DB::table('bills')
            ->where('account_id', $account->id)
            ->orderBy('period_end_date', 'desc')
            ->limit(12)
            ->get()
            ->map(fn($b) => [
                'id'              => $b->id,
                'period_start'    => date('d M Y', strtotime($b->period_start_date)),
                'period_end'      => date('d M Y', strtotime($b->period_end_date)),
                'bill_total'      => number_format($b->bill_total ?? 0, 2),
                'status'          => $b->status ?? 'calculated',
                'is_current'      => $b->status === 'open' || $b->status === 'calculated',
            ])->values();

        return Inertia::render('UserApp/AccountStatement', [
            'account'         => [
                'id'             => $account->id,
                'account_number' => $account->account_number,
                'name_on_bill'   => $account->name_on_bill ?? Auth::user()->name,
            ],
            'bills'           => $bills,
        ]);
    }

    // ------------------------------------------------------------------
    // API: Store Reading (JSON)
    // ------------------------------------------------------------------
    public function storeReading(Request $request)
    {
        $data = $request->validate([
            'meter_id'      => ['required', 'integer', 'exists:meters,id'],
            'reading_date'  => ['required', 'date'],
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_type'  => ['sometimes', 'string', 'in:Actual,Estimated,Provisional'],
        ]);

        // Verify the meter belongs to the authenticated user
        $meter = DB::table('meters')->where('id', $data['meter_id'])->first();
        $account = DB::table('accounts')
            ->where('id', $meter->account_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $id = DB::table('meter_readings')->insertGetId([
            'meter_id'      => $data['meter_id'],
            'reading_date'  => $data['reading_date'],
            'reading_value' => $data['reading_value'],
            'reading_type'  => $data['reading_type'] ?? 'Actual',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // API: Dashboard data (JSON — for period switching via AJAX)
    // ------------------------------------------------------------------
    public function dashboardData(Request $request)
    {
        // Re-use dashboard() logic but return JSON
        $user    = Auth::user();
        $account = $this->getCurrentAccount($user, $request);
        if (!$account) {
            return response()->json(['error' => 'No account'], 404);
        }

        $periodIndex = (int) $request->get('period', 0);
        $meters      = DB::table('meters')->where('account_id', $account->id)->get();

        $waterBill = $electricityBill = null;
        foreach ($meters as $meter) {
            $billData = $this->getBillDataForMeter($meter, $periodIndex);
            if ($meter->type === 'water')       $waterBill       = $billData;
            if ($meter->type === 'electricity') $electricityBill = $billData;
        }

        return response()->json([
            'waterBill'       => $waterBill,
            'electricityBill' => $electricityBill,
        ]);
    }

    // ------------------------------------------------------------------
    // API: Accounts list (JSON)
    // ------------------------------------------------------------------
    public function accountsList()
    {
        $accounts = DB::table('accounts')
            ->where('user_id', Auth::id())
            ->get(['id', 'account_number']);
        return response()->json($accounts);
    }

    // ====================================================================
    // PRIVATE HELPERS
    // ====================================================================

    private function getCurrentAccount($user, Request $request)
    {
        $accountId = $request->session()->get('user_account_id');
        if ($accountId) {
            $account = DB::table('accounts')
                ->where('id', $accountId)
                ->where('user_id', $user->id)
                ->first();
            if ($account) return $account;
        }
        return DB::table('accounts')->where('user_id', $user->id)->first();
    }

    private function getBillDataForMeter(object $meter, int $periodIndex): ?array
    {
        $bills = DB::table('bills')
            ->where('meter_id', $meter->id)
            ->orderBy('period_end_date', 'desc')
            ->get();

        $index = abs($periodIndex);
        $bill  = $bills->get($index);

        if (!$bill) return null;

        // Decode breakdown JSON
        $breakdown = is_string($bill->breakdown ?? null)
            ? json_decode($bill->breakdown, true)
            : ($bill->breakdown ?? []);

        return [
            'bill_id'             => $bill->id,
            'period_start_date'   => $bill->period_start_date,
            'period_end_date'     => $bill->period_end_date,
            'daily_usage'         => $bill->daily_usage ?? 0,
            'total_usage'         => $breakdown['usage_l'] ?? 0,
            'usage_charge'        => number_format($bill->usage_charge ?? 0, 2),
            'fixed_total'         => number_format($bill->fixed_total ?? 0, 2),
            'vat_amount'          => number_format($bill->vat_amount ?? 0, 2),
            'bill_total'          => number_format($bill->bill_total ?? 0, 2),
            'projected_charge'    => number_format($bill->bill_total ?? 0, 2),
            'status'              => $bill->status ?? 'calculated',
            'breakdown'           => $breakdown,
            'consumption_charge'  => number_format($breakdown['consumption_charge'] ?? ($bill->usage_charge ?? 0), 2),
            'discharge_charge'    => number_format($breakdown['discharge_charge'] ?? 0, 2),
            'infrastructure_charge'=> number_format($breakdown['infrastructure_charge'] ?? 0, 2),
            'rates'               => number_format($breakdown['rates'] ?? 0, 2),
        ];
    }

    private function calcReadingDueDays(int $meterId, ?string $periodEndDate): ?int
    {
        if (!$periodEndDate) return null;
        $end  = \Carbon\Carbon::parse($periodEndDate, 'Africa/Johannesburg');
        $now  = now('Africa/Johannesburg');
        $days = (int) $now->diffInDays($end, false);
        return $days >= 0 ? $days : null;
    }

    /** Format water reading as "NNNN - NN" (always 6 digits, 4+2 with leading zeros) */
    public static function formatWaterReading(float|int|string $value): string
    {
        // value stored as decimal, e.g. 4.5 or 9653.53
        $f   = number_format((float) $value, 2, '.', '');
        [$int, $dec] = explode('.', $f);
        return str_pad($int, 4, '0', STR_PAD_LEFT) . ' - ' . str_pad($dec, 2, '0', STR_PAD_RIGHT);
    }

    /** Format electricity reading as "NNNNNN" (always 6 digits) */
    public static function formatElectricityReading(float|int|string $value): string
    {
        return str_pad((string)(int)$value, 6, '0', STR_PAD_LEFT);
    }
}
```

---

## 7. Modify `resources/views/app.blade.php`

Add the Nunito font and user-app CSS. The final file must look like this:

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MyCities-Core') }}</title>
    <!-- SB Admin 2 (Bootstrap 4) + FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="/css/main.css" rel="stylesheet">
    <link href="/css/custom-admin.css" rel="stylesheet">
    <!-- User App design tokens -->
    <link href="/css/user-app.css" rel="stylesheet">
    @routes
    {!! vite(['resources/js/inertia-app.js']) !!}
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

---

## 8. Modify `resources/js/Layouts/AdminLayout.vue`

In the `menuItems` array (around line 94–108), add one entry **before** the
`'Billing Calculator'` item:

```js
{ label: 'App View', icon: 'fas fa-fw fa-mobile-alt', route: 'user.login', routes: ['user.login', 'user.dashboard'], external: true },
```

Then update the `<Link>` in the nav to handle the `external` flag (open in new tab):

Replace the nav `<Link>` template block:
```html
<nav class="nav flex-column">
  <template v-for="item in menuItems" :key="item.route">
    <!-- External link (new tab) -->
    <a
      v-if="item.external"
      :href="route(item.route)"
      target="_blank"
      rel="noopener"
      class="nav-item"
    >
      <i :class="item.icon"></i>
      <span>{{ item.label }}</span>
      <i class="fas fa-external-link-alt" style="margin-left:auto;font-size:0.6rem;opacity:0.6;"></i>
    </a>
    <!-- Internal Inertia link -->
    <Link
      v-else
      :href="route(item.route)"
      class="nav-item"
      :class="{ 'active': isActive(item.routes) }"
    >
      <i :class="item.icon"></i>
      <span>{{ item.label }}</span>
    </Link>
  </template>
</nav>
```

---

## 9. `UserAppLayout.vue`

**Path:** `resources/js/Layouts/UserAppLayout.vue`

This is the shell that wraps every user app page. It renders a phone-width container
(max 414px) centered on desktop, with a fixed bottom navigation bar.

Props:
- `title` (String, optional) — shown in teal header bar
- `showBack` (Boolean, default false) — show ← back button in header
- `backRoute` (String, optional) — route name to navigate back to

```vue
<template>
  <div class="ua-root">
    <div class="ua-phone">

      <!-- TOP BAR -->
      <header class="ua-header">
        <button v-if="showBack" class="ua-back-btn" @click="goBack">
          <i class="fas fa-arrow-left"></i>
        </button>
        <span class="ua-header-title">{{ title || 'MyCities' }}</span>
        <div class="ua-header-logo" v-if="!title">
          <span class="ua-logo-my">My</span><span class="ua-logo-cities">Cities</span>
        </div>
      </header>

      <!-- MAIN SCROLLABLE CONTENT -->
      <main class="ua-content">
        <slot />
      </main>

      <!-- BOTTOM NAV -->
      <nav class="ua-bottom-nav">
        <Link :href="route('user.splash')" class="ua-nav-btn" :class="{ active: isRoute('user.splash') }">
          <i class="fas fa-home"></i>
          <span>Home</span>
        </Link>
        <Link :href="route('user.dashboard')" class="ua-nav-btn" :class="{ active: isRoute('user.dashboard') }">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </Link>
        <Link :href="route('user.reading.water')" class="ua-nav-btn" :class="{ active: isRoute('user.reading.water') || isRoute('user.reading.electricity') }">
          <i class="fas fa-tint"></i>
          <span>Readings</span>
        </Link>
        <Link :href="route('user.account')" class="ua-nav-btn" :class="{ active: isRoute('user.account') }">
          <i class="fas fa-receipt"></i>
          <span>Account</span>
        </Link>
      </nav>

    </div>
  </div>
</template>

<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  title:     { type: String,  default: '' },
  showBack:  { type: Boolean, default: false },
  backRoute: { type: String,  default: '' },
})

const page = usePage()

const isRoute = (name) => page.props.ziggy?.current === name

const goBack = () => {
  window.history.back()
}
</script>

<style scoped>
/* ── Root: full-viewport grey background ── */
.ua-root {
  min-height: 100vh;
  background: var(--ua-bg);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 0;
}

/* ── Phone container ── */
.ua-phone {
  width: 100%;
  max-width: 414px;
  min-height: 100vh;
  background: var(--ua-card);
  display: flex;
  flex-direction: column;
  position: relative;
  box-shadow: 0 0 40px rgba(0,0,0,0.12);
}

/* ── Header ── */
.ua-header {
  background: var(--ua-primary);
  color: #fff;
  height: 56px;
  display: flex;
  align-items: center;
  padding: 0 16px;
  flex-shrink: 0;
  gap: 12px;
}

.ua-header-title {
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.ua-header-logo {
  font-style: italic;
  font-size: 1.25rem;
}

.ua-logo-my     { font-weight: 300; color: #fff; }
.ua-logo-cities { font-weight: 600; color: #fff; }

.ua-back-btn {
  background: none;
  border: none;
  color: #fff;
  font-size: 1rem;
  cursor: pointer;
  padding: 4px 8px 4px 0;
}

/* ── Main scrollable content ── */
.ua-content {
  flex: 1;
  overflow-y: auto;
  background: var(--ua-bg);
  padding-bottom: 64px; /* space for bottom nav */
}

/* ── Bottom navigation ── */
.ua-bottom-nav {
  position: fixed;
  bottom: 0;
  width: 100%;
  max-width: 414px;
  height: 56px;
  background: var(--ua-card);
  border-top: 1px solid var(--ua-divider);
  display: flex;
  z-index: 100;
}

.ua-nav-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--ua-text-secondary);
  text-decoration: none;
  font-size: 0.65rem;
  gap: 2px;
  transition: color 0.15s;
}

.ua-nav-btn i    { font-size: 1.1rem; }
.ua-nav-btn.active { color: var(--ua-primary); font-weight: 600; }
.ua-nav-btn:hover  { color: var(--ua-primary); text-decoration: none; }
</style>
```

---

## 10. `NumericKeypad.vue`

**Path:** `resources/js/Components/NumericKeypad.vue`

Reusable keypad for reading entry. For water: shows `.` key. For electricity: hides it.

Props:
- `showDecimal` (Boolean, default: false) — show `.` key (water only)

Emits:
- `digit(String)` — when 1–9 or 0 pressed
- `decimal()` — when `.` pressed
- `backspace()` — when `⌫` pressed

```vue
<template>
  <div class="keypad">
    <div class="keypad-grid">
      <button
        v-for="key in keys"
        :key="key.id"
        class="keypad-btn"
        :class="{ 'keypad-btn--action': key.isAction, 'keypad-btn--empty': key.isEmpty }"
        :disabled="key.isEmpty"
        @click="handleKey(key)"
      >
        <i v-if="key.icon" :class="key.icon"></i>
        <span v-else>{{ key.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  showDecimal: { type: Boolean, default: false },
})

const emit = defineEmits(['digit', 'decimal', 'backspace'])

// 3×4 grid: rows 1–9 then [. / empty] [0] [⌫]
const keys = [
  { id:'1',  label:'1' },
  { id:'2',  label:'2' },
  { id:'3',  label:'3' },
  { id:'4',  label:'4' },
  { id:'5',  label:'5' },
  { id:'6',  label:'6' },
  { id:'7',  label:'7' },
  { id:'8',  label:'8' },
  { id:'9',  label:'9' },
  { id:'dot',label:'.', isDecimal: true, isEmpty: !props.showDecimal },
  { id:'0',  label:'0' },
  { id:'bs', label:'',  icon: 'fas fa-backspace', isAction: true },
]

function handleKey(key) {
  if (key.isEmpty) return
  if (key.isDecimal) { emit('decimal'); return }
  if (key.isAction)  { emit('backspace'); return }
  emit('digit', key.label)
}
</script>

<style scoped>
.keypad {
  padding: 8px 16px 16px;
  background: var(--ua-card);
}

.keypad-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.keypad-btn {
  height: 56px;
  border: 1px solid var(--ua-divider);
  border-radius: var(--ua-radius-sm);
  background: var(--ua-card);
  color: var(--ua-text);
  font-size: 1.25rem;
  font-weight: 500;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: background 0.1s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.keypad-btn:active,
.keypad-btn:hover   { background: var(--ua-bg); }
.keypad-btn--action { background: #FFF3E0; color: var(--ua-orange-dark); border-color: #FFCC80; }
.keypad-btn--action:hover { background: #FFE0B2; }
.keypad-btn--empty  { visibility: hidden; cursor: default; }
</style>
```

---

## 11. `MeterDisplay.vue`

**Path:** `resources/js/Components/MeterDisplay.vue`

Shows the large reading display above the keypad.

Props:
- `digits` (Array of 6 Numbers) — current digit state
- `type` (`'water'` | `'electricity'`)

```vue
<template>
  <div class="meter-display">
    <template v-if="type === 'water'">
      <span class="meter-int">{{ pad(digits.slice(0,4)) }}</span>
      <span class="meter-sep"> - </span>
      <span class="meter-dec">{{ pad(digits.slice(4,6)) }}</span>
    </template>
    <template v-else>
      <span class="meter-int">{{ pad(digits) }}</span>
    </template>
  </div>
</template>

<script setup>
defineProps({
  digits: { type: Array,  required: true }, // 6-element array of numbers
  type:   { type: String, default: 'water' },
})

function pad(arr) {
  return arr.map(d => String(d)).join('')
}
</script>

<style scoped>
.meter-display {
  background: var(--ua-card);
  border: 2px solid var(--ua-primary);
  border-radius: var(--ua-radius);
  margin: 12px 16px;
  padding: 16px;
  text-align: center;
  font-size: 2.5rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--ua-text);
  font-family: 'Courier New', monospace;
}

.meter-sep {
  color: var(--ua-primary);
  margin: 0 4px;
}
</style>
```

---

## 12. `Pages/UserApp/Splash.vue`

Shows an ad (if any) for 3 seconds then auto-forwards. Skip button always visible.

Props from controller:
- `ad` — `{ id, title, content, image }` or `null`
- `nextUrl` — URL string to navigate to after splash

```vue
<template>
  <div class="splash-root">
    <div class="splash-phone">

      <!-- HEADER -->
      <div class="splash-header">
        <span class="splash-logo-my">My</span><span class="splash-logo-cities">Cities</span>
      </div>

      <!-- AD CONTENT -->
      <div class="splash-body">
        <template v-if="ad">
          <img v-if="ad.image" :src="ad.image" class="splash-image" alt="Advertisement">
          <h2 class="splash-title">{{ ad.title }}</h2>
          <div class="splash-content" v-html="ad.content"></div>
        </template>
        <template v-else>
          <div class="splash-default">
            <i class="fas fa-city splash-icon"></i>
            <h2>Welcome to MyCities</h2>
            <p>Your municipal services portal</p>
          </div>
        </template>
      </div>

      <!-- FOOTER: countdown + skip -->
      <div class="splash-footer">
        <div class="splash-progress">
          <div class="splash-progress-bar" :style="{ width: progressPct + '%' }"></div>
        </div>
        <a :href="nextUrl" class="splash-skip">Skip →</a>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  ad:      { type: Object, default: null },
  nextUrl: { type: String, required: true },
})

const DURATION = 3000 // ms
const progressPct = ref(0)
let timer = null
let raf   = null
const startTime = ref(0)

onMounted(() => {
  startTime.value = Date.now()

  const animate = () => {
    const elapsed = Date.now() - startTime.value
    progressPct.value = Math.min((elapsed / DURATION) * 100, 100)
    if (elapsed < DURATION) {
      raf = requestAnimationFrame(animate)
    } else {
      window.location.href = props.nextUrl
    }
  }

  raf = requestAnimationFrame(animate)
})

onUnmounted(() => {
  cancelAnimationFrame(raf)
  clearTimeout(timer)
})
</script>

<style scoped>
.splash-root {
  min-height: 100vh;
  background: var(--ua-primary);
  display: flex;
  justify-content: center;
  align-items: center;
}

.splash-phone {
  width: 100%;
  max-width: 414px;
  min-height: 100vh;
  background: var(--ua-card);
  display: flex;
  flex-direction: column;
}

.splash-header {
  background: var(--ua-primary);
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-style: italic;
  font-size: 2rem;
}

.splash-logo-my     { font-weight: 300; color: #fff; }
.splash-logo-cities { font-weight: 700; color: #fff; }

.splash-body {
  flex: 1;
  padding: 32px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.splash-image {
  max-width: 100%;
  max-height: 260px;
  object-fit: contain;
  border-radius: var(--ua-radius);
  margin-bottom: 20px;
}

.splash-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--ua-primary);
  margin-bottom: 12px;
}

.splash-content { color: var(--ua-text); line-height: 1.6; }

.splash-default { text-align: center; }
.splash-icon    { font-size: 4rem; color: var(--ua-primary); margin-bottom: 16px; display: block; }

.splash-footer {
  padding: 16px;
}

.splash-progress {
  height: 4px;
  background: var(--ua-divider);
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 12px;
}

.splash-progress-bar {
  height: 100%;
  background: var(--ua-primary);
  transition: width 0.1s linear;
}

.splash-skip {
  display: block;
  text-align: right;
  color: var(--ua-primary);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
}
</style>
```

---

## 13. `Pages/UserApp/Login.vue`

User login — styled in teal, separate from the admin login.

Props: none from controller (flash messages come from `$page.props.flash`).

```vue
<template>
  <div class="login-root">
    <div class="login-phone">

      <!-- HEADER -->
      <div class="login-header">
        <div class="login-logo">
          <span class="logo-my">My</span><span class="logo-cities">Cities</span>
        </div>
        <p class="login-tagline">Municipal Services Portal</p>
      </div>

      <!-- FORM -->
      <div class="login-body">
        <div v-if="$page.props.flash?.message" class="ua-alert">
          {{ $page.props.flash.message }}
        </div>

        <div v-if="form.errors.email" class="ua-alert ua-alert--error">
          {{ form.errors.email }}
        </div>

        <form @submit.prevent="submit">
          <div class="ua-field">
            <label class="ua-label">Email Address</label>
            <input
              type="email"
              v-model="form.email"
              class="ua-input"
              placeholder="you@example.com"
              autocomplete="email"
              required
            >
          </div>

          <div class="ua-field">
            <label class="ua-label">Password</label>
            <div class="ua-input-wrap">
              <input
                :type="showPwd ? 'text' : 'password'"
                v-model="form.password"
                class="ua-input"
                placeholder="Your password"
                autocomplete="current-password"
                required
              >
              <button type="button" class="ua-pwd-toggle" @click="showPwd = !showPwd">
                <i :class="showPwd ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
              </button>
            </div>
          </div>

          <div class="ua-field ua-field--row">
            <input type="checkbox" v-model="form.remember" id="remember">
            <label for="remember" class="ua-label-inline">Keep me logged in</label>
          </div>

          <button type="submit" class="ua-btn-primary" :disabled="form.processing">
            <i class="fas fa-sign-in-alt"></i> Sign In
          </button>
        </form>
      </div>

      <!-- FOOTER -->
      <div class="login-footer">
        <p>By signing in you agree to the MyCities terms of service.</p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const showPwd = ref(false)

const form = useForm({
  email:    '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('user.login.submit'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<style scoped>
.login-root {
  min-height: 100vh;
  background: var(--ua-primary);
  display: flex;
  justify-content: center;
  align-items: center;
}

.login-phone {
  width: 100%;
  max-width: 414px;
  min-height: 100vh;
  background: var(--ua-card);
  display: flex;
  flex-direction: column;
}

.login-header {
  background: var(--ua-primary);
  padding: 48px 24px 32px;
  text-align: center;
}

.login-logo {
  font-style: italic;
  font-size: 2.5rem;
  margin-bottom: 8px;
}

.logo-my     { font-weight: 300; color: #fff; }
.logo-cities { font-weight: 700; color: #fff; }

.login-tagline {
  color: rgba(255,255,255,0.85);
  font-size: 0.9rem;
  margin: 0;
}

.login-body {
  flex: 1;
  padding: 32px 24px;
}

.ua-alert {
  background: #E3F2FD;
  color: #1565C0;
  border-radius: var(--ua-radius-sm);
  padding: 10px 14px;
  margin-bottom: 16px;
  font-size: 0.9rem;
}

.ua-alert--error {
  background: #FFEBEE;
  color: #C62828;
}

.ua-field {
  margin-bottom: 20px;
}

.ua-field--row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ua-label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--ua-text-secondary);
  margin-bottom: 6px;
}

.ua-label-inline {
  font-size: 0.85rem;
  color: var(--ua-text-secondary);
  cursor: pointer;
}

.ua-input {
  width: 100%;
  padding: 12px 14px;
  border: 1.5px solid var(--ua-divider);
  border-radius: var(--ua-radius-sm);
  font-size: 1rem;
  font-family: 'Nunito', sans-serif;
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.ua-input:focus { border-color: var(--ua-primary); }

.ua-input-wrap {
  position: relative;
}

.ua-input-wrap .ua-input {
  padding-right: 44px;
}

.ua-pwd-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--ua-text-secondary);
}

.ua-btn-primary {
  width: 100%;
  padding: 14px;
  background: var(--ua-primary);
  color: #fff;
  border: none;
  border-radius: var(--ua-radius-sm);
  font-size: 1rem;
  font-weight: 600;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.ua-btn-primary:hover    { background: var(--ua-primary-dark); }
.ua-btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }

.login-footer {
  padding: 16px 24px;
  text-align: center;
  font-size: 0.75rem;
  color: var(--ua-text-secondary);
  border-top: 1px solid var(--ua-divider);
}
</style>
```

---

## 14. `Pages/UserApp/Dashboard.vue`

The main billing dashboard. Two collapsible sections (Water, Electricity).

Props from controller:
```
accounts          Array  [{ id, account_number }]
currentAccount    Object { id, account_number }
waterBill         Object|null  (see getBillDataForMeter() shape)
electricityBill   Object|null
readingDueInDays  Number|null
periodLabel       String|null  "DD Mon YYYY to DD Mon YYYY"
periodIndex       Number       0 = current, -1, -2 for past
today             String       "DD Mon YYYY"
```

```vue
<template>
  <UserAppLayout>

    <!-- TEAL TOP BAR: date + period nav -->
    <div class="dash-topbar">
      <button class="dash-nav-btn" @click="changePeriod(-1)" :disabled="loading">◄</button>
      <div class="dash-topbar-center">
        <span class="dash-date">Current date: {{ today }}</span>
        <span v-if="readingDueInDays !== null" class="dash-due-pill">
          First Reading due in {{ readingDueInDays }} days
        </span>
      </div>
      <button class="dash-nav-btn" @click="changePeriod(1)" :disabled="loading || currentPeriodIndex >= 0">►</button>
    </div>

    <!-- PERIOD LABEL -->
    <div class="dash-period" v-if="periodLabel">
      Period: {{ periodLabel }}
    </div>

    <!-- GRAND TOTAL -->
    <div class="dash-grand-total">
      R{{ grandTotal }}
    </div>

    <!-- WATER SECTION -->
    <div class="dash-section" v-if="waterData">
      <div class="dash-section-header" @click="waterExpanded = !waterExpanded">
        <div class="dash-section-left">
          <i class="fas fa-tint dash-icon dash-icon--water"></i>
          <span class="dash-section-name">Water</span>
        </div>
        <div class="dash-section-stats">
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Usage</span>
            <span class="dash-stat-value">{{ waterData.daily_usage }} L</span>
          </div>
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Cost</span>
            <span class="dash-stat-value">R{{ dailyCost(waterData) }}</span>
          </div>
        </div>
        <div class="dash-section-actions">
          <Link :href="route('user.reading.water')" class="dash-link" @click.stop>Enter reading</Link>
          <span class="dash-link-sep">|</span>
          <Link :href="route('user.reading.history')" class="dash-link" @click.stop>View History</Link>
          <span class="dash-link-sep">|</span>
          <button class="dash-link" @click.stop="waterExpanded = !waterExpanded">
            {{ waterExpanded ? 'Hide Details' : 'Show Details' }}
          </button>
        </div>
      </div>

      <!-- Expanded water details -->
      <div class="dash-section-detail" v-if="waterExpanded">
        <div class="dash-proj-line">
          Projected charges &rarr; <span class="dash-proj-amount">R {{ waterData.projected_charge }}</span>
        </div>
        <div class="dash-breakdown">
          <div class="dash-breakdown-row">
            <span>Consumption Charges</span>
            <span>R{{ waterData.consumption_charge }}</span>
          </div>
          <div class="dash-breakdown-row">
            <span>Discharge charges</span>
            <span>R{{ waterData.discharge_charge }}</span>
          </div>
          <div class="dash-breakdown-row">
            <span>Infrastructure Surcharge</span>
            <span>R{{ waterData.infrastructure_charge }}</span>
          </div>
          <div class="dash-breakdown-row">
            <span>VAT</span>
            <span>R{{ waterData.vat_amount }}</span>
          </div>
          <div class="dash-breakdown-row">
            <span>Rates</span>
            <span>R{{ waterData.rates }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ELECTRICITY SECTION -->
    <div class="dash-section" v-if="electricityData">
      <div class="dash-section-header" @click="elecExpanded = !elecExpanded">
        <div class="dash-section-left">
          <i class="fas fa-bolt dash-icon dash-icon--elec"></i>
          <span class="dash-section-name">Electricity</span>
        </div>
        <div class="dash-section-stats">
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Usage</span>
            <span class="dash-stat-value">{{ electricityData.daily_usage }} kWh</span>
          </div>
          <div class="dash-stat">
            <span class="dash-stat-label">Daily Cost</span>
            <span class="dash-stat-value">R{{ dailyCost(electricityData) }}</span>
          </div>
        </div>
        <div class="dash-section-actions">
          <Link :href="route('user.reading.electricity')" class="dash-link" @click.stop>Enter reading</Link>
          <span class="dash-link-sep">|</span>
          <Link :href="route('user.reading.history')" class="dash-link" @click.stop>View History</Link>
          <span class="dash-link-sep">|</span>
          <button class="dash-link" @click.stop="elecExpanded = !elecExpanded">
            {{ elecExpanded ? 'Hide Details' : 'Show Details' }}
          </button>
        </div>
      </div>

      <div class="dash-section-detail" v-if="elecExpanded">
        <div class="dash-proj-line">
          Projected charges &rarr; <span class="dash-proj-amount">R {{ electricityData.projected_charge }}</span>
        </div>
        <div class="dash-breakdown">
          <div class="dash-breakdown-row">
            <span>Consumption Charges</span>
            <span>R{{ electricityData.consumption_charge }}</span>
          </div>
          <div class="dash-breakdown-row">
            <span>VAT</span>
            <span>R{{ electricityData.vat_amount }}</span>
          </div>
          <div class="dash-breakdown-row">
            <span>Rates</span>
            <span>R{{ electricityData.rates }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- TOTAL LINE -->
    <div class="dash-total-line" v-if="waterData || electricityData">
      <span class="dash-total-label">Total</span>
      <span class="dash-total-amount">R{{ grandTotal }}</span>
    </div>

    <!-- EMPTY STATE -->
    <div class="dash-empty" v-if="!waterData && !electricityData">
      <i class="fas fa-info-circle"></i>
      <p>No billing data available yet. Your account is being set up.</p>
    </div>

  </UserAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

const props = defineProps({
  accounts:         { type: Array,  default: () => [] },
  currentAccount:   { type: Object, default: null },
  waterBill:        { type: Object, default: null },
  electricityBill:  { type: Object, default: null },
  readingDueInDays: { type: Number, default: null },
  periodLabel:      { type: String, default: '' },
  periodIndex:      { type: Number, default: 0 },
  today:            { type: String, default: '' },
})

const waterExpanded = ref(false)
const elecExpanded  = ref(false)
const loading       = ref(false)
const currentPeriodIndex = ref(props.periodIndex)

const waterData       = computed(() => props.waterBill)
const electricityData = computed(() => props.electricityBill)

const grandTotal = computed(() => {
  const w = parseFloat(props.waterBill?.bill_total?.replace(',','') ?? 0)
  const e = parseFloat(props.electricityBill?.bill_total?.replace(',','') ?? 0)
  return (w + e).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
})

const dailyCost = (bill) => {
  if (!bill || !bill.bill_total || !bill.period_start_date || !bill.period_end_date) return '0.00'
  const start = new Date(bill.period_start_date)
  const end   = new Date(bill.period_end_date)
  const days  = Math.max(1, Math.round((end - start) / 86400000) + 1)
  const total = parseFloat(bill.bill_total.replace(',',''))
  return (total / days).toFixed(2)
}

const changePeriod = (direction) => {
  const newIndex = currentPeriodIndex.value - direction  // direction=1 means go back (more negative)
  // Prevent going into the future (index cannot be > 0 in our scheme, 0 is current)
  if (newIndex > 0) return
  currentPeriodIndex.value = newIndex
  loading.value = true
  router.get(route('user.dashboard'), { period: newIndex }, {
    preserveState: false,
    onFinish: () => { loading.value = false }
  })
}
</script>

<style scoped>
.dash-topbar {
  background: var(--ua-primary);
  color: #fff;
  display: flex;
  align-items: center;
  padding: 8px 12px;
  gap: 8px;
  min-height: 48px;
}

.dash-nav-btn {
  background: rgba(255,255,255,0.2);
  border: none;
  color: #fff;
  border-radius: 4px;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dash-nav-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.dash-nav-btn:hover:not(:disabled) { background: rgba(255,255,255,0.3); }

.dash-topbar-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.dash-date {
  font-size: 0.8rem;
  font-weight: 500;
}

.dash-due-pill {
  background: var(--ua-orange);
  color: #fff;
  border-radius: 12px;
  padding: 2px 10px;
  font-size: 0.7rem;
  font-weight: 600;
}

.dash-period {
  background: var(--ua-primary);
  color: rgba(255,255,255,0.85);
  text-align: center;
  font-size: 0.75rem;
  padding: 4px;
}

.dash-grand-total {
  font-size: 2.4rem;
  font-weight: 700;
  color: var(--ua-amount);
  text-align: center;
  padding: 16px;
  background: var(--ua-card);
}

.dash-section {
  background: var(--ua-card);
  border-bottom: 1px solid var(--ua-divider);
  margin-bottom: 1px;
}

.dash-section-header {
  padding: 12px 16px;
  cursor: pointer;
}

.dash-section-left {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.dash-section-name {
  font-weight: 600;
  font-size: 0.95rem;
}

.dash-icon        { font-size: 1.1rem; }
.dash-icon--water { color: var(--ua-water); }
.dash-icon--elec  { color: var(--ua-electricity); }

.dash-section-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 4px;
  background: var(--ua-bg);
  border-radius: var(--ua-radius-sm);
  padding: 8px;
  margin-bottom: 8px;
}

.dash-stat { text-align: center; }
.dash-stat-label { display: block; font-size: 0.7rem; color: var(--ua-text-secondary); }
.dash-stat-value { display: block; font-size: 0.9rem; font-weight: 600; color: var(--ua-text); }

.dash-section-actions {
  display: flex;
  gap: 4px;
  align-items: center;
}

.dash-link {
  background: none;
  border: none;
  color: var(--ua-primary);
  font-size: 0.78rem;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  text-decoration: none;
  padding: 0;
}

.dash-link:hover { text-decoration: underline; }
.dash-link-sep   { color: var(--ua-text-secondary); font-size: 0.78rem; }

.dash-section-detail {
  padding: 8px 16px 16px;
  border-top: 1px solid var(--ua-divider);
}

.dash-proj-line {
  font-size: 0.85rem;
  color: var(--ua-text-secondary);
  margin-bottom: 10px;
}

.dash-proj-amount {
  font-weight: 700;
  font-size: 1rem;
  color: var(--ua-text);
}

.dash-breakdown { display: flex; flex-direction: column; gap: 4px; }

.dash-breakdown-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.82rem;
  color: var(--ua-text);
}

.dash-total-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: var(--ua-card);
  border-top: 2px solid var(--ua-primary);
}

.dash-total-label  { font-size: 1rem; font-weight: 600; color: var(--ua-primary); }
.dash-total-amount { font-size: 1.6rem; font-weight: 700; color: var(--ua-amount); }

.dash-empty {
  padding: 40px 24px;
  text-align: center;
  color: var(--ua-text-secondary);
}

.dash-empty i { font-size: 2rem; margin-bottom: 12px; display: block; }
</style>
```

---

## 15. `Pages/UserApp/WaterReading.vue`

The reading entry screen for water. Uses NumericKeypad and MeterDisplay components.

**Meter reading input logic (right-to-left, 6 positions):**
- `digits` is a 6-element array: indices 0–3 = integer part, 4–5 = decimal
- Each new digit pushes all existing digits left by one, enters new digit at position 5
- Backspace shifts all digits right by one, zeroes position 0
- Display: always `NNNN - NN` (positions 0–3 joined, dash, positions 4–5 joined)
- Value for submission: `parseFloat("NNNN.NN")`

Props from controller:
```
meter       { id, meter_number, start_reading, start_date }
readings    Array of { id, reading_date, reading_value, reading_type }
periodLabel String|null
```

```vue
<template>
  <UserAppLayout title="ENTER READINGS" :showBack="true" backRoute="user.dashboard">

    <!-- ORANGE PERIOD SUBHEADER -->
    <div class="reading-subheader" v-if="periodLabel">
      <button class="reading-nav-btn">◄</button>
      <span>Period: {{ periodLabel }}</span>
      <button class="reading-nav-btn">►</button>
    </div>

    <!-- SECTION HEADER: Water -->
    <div class="reading-section-header">
      <i class="fas fa-tint reading-type-icon reading-type-icon--water"></i>
      <span class="reading-type-label">Water</span>
    </div>

    <!-- METER INFO -->
    <div class="reading-meter-info">
      Meter Number #{{ meter.meter_number }}
    </div>

    <!-- READING HISTORY LIST -->
    <div class="reading-history-list">
      <div v-if="meter.start_date" class="reading-history-row reading-history-row--start">
        <span class="rh-date">Start Reading {{ meter.start_date }}</span>
        <span class="rh-value">{{ meter.start_reading }}</span>
        <span class="rh-badge rh-badge--estimated">Estimated</span>
      </div>

      <div v-for="r in readings" :key="r.id" class="reading-history-row">
        <span class="rh-date">{{ r.reading_date }}</span>
        <span class="rh-value">{{ r.reading_value }}</span>
        <span class="rh-badge" :class="badgeClass(r.reading_type)">{{ r.reading_type }}</span>
      </div>

      <div v-if="readings.length === 0 && !addingNew" class="reading-empty">
        Start your first reading now. Once more than 24 hours have passed you can add another reading.
      </div>
    </div>

    <!-- ADD NEW READING TOGGLE -->
    <button v-if="!addingNew" class="reading-add-btn" @click="addingNew = true">
      + Add new reading
    </button>

    <!-- DATE SELECTOR (when adding) -->
    <div v-if="addingNew" class="reading-date-row">
      <label class="ua-label">Reading Date</label>
      <input type="date" v-model="readingDate" class="ua-input" :max="today">
    </div>

    <!-- METER DISPLAY (when adding) -->
    <MeterDisplay v-if="addingNew" :digits="digits" type="water" />

    <!-- KEYPAD (when adding) -->
    <NumericKeypad
      v-if="addingNew"
      :showDecimal="false"
      @digit="onDigit"
      @backspace="onBackspace"
    />

    <!-- ACTION BUTTONS (when adding) -->
    <div v-if="addingNew" class="reading-actions">
      <button class="reading-btn-cancel" @click="cancelAdd">CANCEL</button>
      <button class="reading-btn-enter" @click="submitReading" :disabled="submitting">ENTER</button>
    </div>

    <!-- SUCCESS MESSAGE -->
    <div v-if="successMsg" class="reading-success">
      {{ successMsg }}
    </div>

  </UserAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import UserAppLayout from '@/Layouts/UserAppLayout.vue'
import NumericKeypad from '@/Components/NumericKeypad.vue'
import MeterDisplay  from '@/Components/MeterDisplay.vue'

const props = defineProps({
  meter:       { type: Object, required: true },
  readings:    { type: Array,  default: () => [] },
  periodLabel: { type: String, default: null },
})

const addingNew   = ref(false)
const submitting  = ref(false)
const successMsg  = ref('')
const readingDate = ref(new Date().toISOString().split('T')[0])
const today       = new Date().toISOString().split('T')[0]

// 6-element digits array (right-to-left input)
const digits = ref([0, 0, 0, 0, 0, 0])

const onDigit = (d) => {
  // Shift all left, put new digit at position 5
  digits.value = [...digits.value.slice(1), parseInt(d)]
}

const onBackspace = () => {
  // Shift all right, put 0 at position 0
  digits.value = [0, ...digits.value.slice(0, 5)]
}

const numericValue = computed(() => {
  const intPart = digits.value.slice(0, 4).join('')
  const decPart = digits.value.slice(4, 6).join('')
  return parseFloat(`${intPart}.${decPart}`)
})

const cancelAdd = () => {
  addingNew.value = false
  digits.value = [0, 0, 0, 0, 0, 0]
  readingDate.value = today
}

const submitReading = async () => {
  if (numericValue.value <= 0) return
  submitting.value = true
  successMsg.value = ''
  try {
    const res = await fetch(route('user.api.reading.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
      },
      body: JSON.stringify({
        meter_id:      props.meter.id,
        reading_date:  readingDate.value,
        reading_value: numericValue.value,
        reading_type:  'Actual',
      }),
    })
    if (res.ok) {
      successMsg.value = 'Reading saved successfully.'
      addingNew.value  = false
      digits.value     = [0, 0, 0, 0, 0, 0]
      // Reload page to show new reading in history
      router.reload({ only: ['readings'] })
    } else {
      successMsg.value = 'Error saving reading. Please try again.'
    }
  } catch (e) {
    successMsg.value = 'Network error. Please try again.'
  } finally {
    submitting.value = false
  }
}

const badgeClass = (type) => ({
  'rh-badge--actual':      type === 'Actual',
  'rh-badge--estimated':   type === 'Estimated',
  'rh-badge--provisional': type === 'Provisional',
})
</script>

<style scoped>
.reading-subheader {
  background: var(--ua-orange);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  font-size: 0.78rem;
  font-weight: 600;
}

.reading-nav-btn {
  background: rgba(255,255,255,0.2);
  border: none;
  color: #fff;
  border-radius: 4px;
  width: 28px;
  height: 28px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.reading-section-header {
  background: var(--ua-card);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid var(--ua-divider);
}

.reading-type-icon       { font-size: 1.2rem; }
.reading-type-icon--water { color: var(--ua-water); }

.reading-type-label {
  font-size: 1rem;
  font-weight: 600;
}

.reading-meter-info {
  padding: 8px 16px;
  font-size: 0.82rem;
  color: var(--ua-text-secondary);
  background: var(--ua-card);
}

.reading-history-list {
  background: var(--ua-card);
  padding: 4px 16px;
}

.reading-history-row {
  display: flex;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid var(--ua-divider);
  font-size: 0.82rem;
  gap: 8px;
}

.reading-history-row--start {
  color: var(--ua-text-secondary);
}

.rh-date  { flex: 1; color: var(--ua-text-secondary); }
.rh-value { font-weight: 600; font-family: 'Courier New', monospace; color: var(--ua-text); min-width: 80px; }

.rh-badge {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 10px;
  text-transform: uppercase;
}

.rh-badge--actual      { background: #E8F5E9; color: #2E7D32; }
.rh-badge--estimated   { background: #F5F5F5; color: var(--ua-grey); }
.rh-badge--provisional { background: #FFF8E1; color: var(--ua-amber); }

.reading-empty {
  padding: 16px 0;
  font-size: 0.82rem;
  color: var(--ua-text-secondary);
  text-align: center;
}

.reading-add-btn {
  display: block;
  width: calc(100% - 32px);
  margin: 12px 16px;
  padding: 10px;
  background: none;
  border: 1.5px dashed var(--ua-primary);
  border-radius: var(--ua-radius-sm);
  color: var(--ua-primary);
  font-size: 0.9rem;
  font-weight: 600;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
}

.reading-add-btn:hover { background: #E0F7FA; }

.reading-date-row {
  padding: 8px 16px;
  background: var(--ua-card);
}

.ua-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ua-text-secondary);
  margin-bottom: 4px;
}

.ua-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid var(--ua-divider);
  border-radius: var(--ua-radius-sm);
  font-size: 0.95rem;
  font-family: 'Nunito', sans-serif;
  box-sizing: border-box;
}

.ua-input:focus { outline: none; border-color: var(--ua-primary); }

.reading-actions {
  display: flex;
  gap: 12px;
  padding: 12px 16px 24px;
  background: var(--ua-card);
}

.reading-btn-cancel,
.reading-btn-enter {
  flex: 1;
  padding: 14px;
  border-radius: var(--ua-radius-sm);
  font-size: 0.95rem;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  letter-spacing: 0.05em;
}

.reading-btn-cancel {
  background: var(--ua-card);
  border: 2px solid var(--ua-primary);
  color: var(--ua-primary);
}

.reading-btn-enter {
  background: var(--ua-primary);
  border: 2px solid var(--ua-primary);
  color: #fff;
}

.reading-btn-enter:hover    { background: var(--ua-primary-dark); border-color: var(--ua-primary-dark); }
.reading-btn-cancel:hover   { background: #E0F7FA; }
.reading-btn-enter:disabled { opacity: 0.6; cursor: not-allowed; }

.reading-success {
  margin: 8px 16px;
  padding: 10px 14px;
  background: #E8F5E9;
  color: #2E7D32;
  border-radius: var(--ua-radius-sm);
  font-size: 0.85rem;
}
</style>
```

---

## 16. `Pages/UserApp/ElectricityReading.vue`

Identical structure to `WaterReading.vue` with these differences:
1. No `.` key: `<NumericKeypad :showDecimal="false" ...>`
2. Section header uses electricity icon and color:
   ```html
   <i class="fas fa-bolt reading-type-icon reading-type-icon--elec"></i>
   <span class="reading-type-label">Electricity</span>
   ```
   Add to `<style>`: `.reading-type-icon--elec { color: var(--ua-electricity); }`
3. MeterDisplay `type="electricity"` (6 integer digits, no dash separator)
4. `numericValue` is an integer:
   ```js
   const numericValue = computed(() => parseInt(digits.value.join('')))
   ```
5. History unit label is `kWh` not `kL`
6. Props from controller: same shape (`meter`, `readings`, `periodLabel`)

**Copy `WaterReading.vue` and apply the 5 changes listed above.**

---

## 17. `Pages/UserApp/ReadingHistory.vue`

Shows all readings across both meters, most recent first.

Props from controller:
```
readings  Array of { id, meter_type, reading_date, reading_value, reading_type }
```

```vue
<template>
  <UserAppLayout title="READING HISTORY" :showBack="true">
    <div class="rh-page">

      <div v-if="readings.length === 0" class="rh-empty">
        <i class="fas fa-history"></i>
        <p>No readings recorded yet.</p>
      </div>

      <div v-for="r in readings" :key="r.id" class="rh-row">
        <div class="rh-row-icon">
          <i class="fas" :class="r.meter_type === 'water' ? 'fa-tint' : 'fa-bolt'"
             :style="{ color: r.meter_type === 'water' ? 'var(--ua-water)' : 'var(--ua-electricity)' }">
          </i>
        </div>
        <div class="rh-row-body">
          <span class="rh-row-date">{{ r.reading_date }}</span>
          <span class="rh-row-value">{{ r.reading_value }}</span>
        </div>
        <span class="rh-badge" :class="badgeClass(r.reading_type)">{{ r.reading_type }}</span>
      </div>

    </div>
  </UserAppLayout>
</template>

<script setup>
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

defineProps({
  readings: { type: Array, default: () => [] },
})

const badgeClass = (type) => ({
  'rh-badge--actual':      type === 'Actual',
  'rh-badge--estimated':   type === 'Estimated',
  'rh-badge--provisional': type === 'Provisional',
})
</script>

<style scoped>
.rh-page { background: var(--ua-card); min-height: 100%; }

.rh-empty {
  padding: 48px 24px;
  text-align: center;
  color: var(--ua-text-secondary);
}

.rh-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; }

.rh-row {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--ua-divider);
  gap: 12px;
}

.rh-row-icon  { width: 28px; text-align: center; font-size: 1rem; }

.rh-row-body  { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.rh-row-date  { font-size: 0.78rem; color: var(--ua-text-secondary); }
.rh-row-value { font-size: 0.95rem; font-weight: 600; font-family: 'Courier New', monospace; }

.rh-badge {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: uppercase;
}

.rh-badge--actual      { background: #E8F5E9; color: #2E7D32; }
.rh-badge--estimated   { background: #F5F5F5; color: var(--ua-grey); }
.rh-badge--provisional { background: #FFF8E1; color: var(--ua-amber); }
</style>
```

---

## 18. `Pages/UserApp/AccountStatement.vue`

Billing periods list with balance overview.

Props from controller:
```
account  { id, account_number, name_on_bill }
bills    Array of { id, period_start, period_end, bill_total, status, is_current }
```

```vue
<template>
  <UserAppLayout title="ACCOUNT" :showBack="true">
    <div class="acct-page">

      <!-- ACCOUNT HEADER -->
      <div class="acct-header">
        <div class="acct-name">{{ account.name_on_bill }}</div>
        <div class="acct-number">Account #{{ account.account_number }}</div>
      </div>

      <!-- BILLING PERIODS -->
      <div v-if="bills.length === 0" class="acct-empty">
        <i class="fas fa-file-invoice-dollar"></i>
        <p>No billing history yet.</p>
      </div>

      <div v-for="bill in bills" :key="bill.id" class="acct-bill-card"
           :class="{ 'acct-bill-card--current': bill.is_current }">
        <div class="acct-bill-period">
          {{ bill.period_start }} – {{ bill.period_end }}
        </div>
        <div class="acct-bill-row">
          <span class="acct-bill-label">Charges</span>
          <span class="acct-bill-amount">R{{ bill.bill_total }}</span>
        </div>
        <div class="acct-bill-status">
          <span class="rh-badge" :class="statusClass(bill.status)">{{ bill.status }}</span>
        </div>
      </div>

    </div>
  </UserAppLayout>
</template>

<script setup>
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

defineProps({
  account: { type: Object, required: true },
  bills:   { type: Array,  default: () => [] },
})

const statusClass = (status) => ({
  'rh-badge--actual':      status === 'calculated' || status === 'paid',
  'rh-badge--estimated':   status === 'open',
  'rh-badge--provisional': status === 'provisional',
})
</script>

<style scoped>
.acct-page { background: var(--ua-bg); min-height: 100%; }

.acct-header {
  background: var(--ua-primary);
  color: #fff;
  padding: 20px 16px;
  text-align: center;
}

.acct-name   { font-size: 1.1rem; font-weight: 700; }
.acct-number { font-size: 0.8rem; opacity: 0.85; margin-top: 4px; }

.acct-empty {
  padding: 48px 24px;
  text-align: center;
  color: var(--ua-text-secondary);
}

.acct-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; }

.acct-bill-card {
  background: var(--ua-card);
  margin: 8px;
  border-radius: var(--ua-radius);
  padding: 14px 16px;
  box-shadow: var(--ua-shadow);
}

.acct-bill-card--current {
  border-left: 4px solid var(--ua-primary);
}

.acct-bill-period {
  font-size: 0.78rem;
  color: var(--ua-text-secondary);
  margin-bottom: 6px;
}

.acct-bill-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.acct-bill-label  { font-size: 0.9rem; color: var(--ua-text); }
.acct-bill-amount { font-size: 1.1rem; font-weight: 700; color: var(--ua-amount); }

.acct-bill-status { display: flex; justify-content: flex-end; }

.rh-badge {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: uppercase;
}

.rh-badge--actual      { background: #E8F5E9; color: #2E7D32; }
.rh-badge--estimated   { background: #F5F5F5; color: #9E9E9E; }
.rh-badge--provisional { background: #FFF8E1; color: #FF8F00; }
</style>
```

---

## 19. Build Steps

After creating all files, run the following in order:

**Step 1 — PHP syntax check (every new PHP file)**
```powershell
php -l "c:\Docker\MyCities-Core\app\Http\Controllers\User\UserAuthController.php"
php -l "c:\Docker\MyCities-Core\app\Http\Controllers\User\UserAppController.php"
```

**Step 2 — Duplicate method check (web.php)**
```powershell
Select-String -Path "c:\Docker\MyCities-Core\routes\web.php" -Pattern "Route::(get|post|put|delete)" | Measure-Object
```

**Step 3 — Build frontend assets**
```powershell
cd c:\Docker\MyCities-Core
npm run build
```

**Step 4 — Rebuild Docker**
```powershell
cd c:\Docker\MyCities-Core\infrastructure
docker-compose build laravel
docker-compose up -d
```

**Step 5 — Clear caches**
```powershell
docker exec mycities-core-laravel php artisan route:clear
docker exec mycities-core-laravel php artisan view:clear
docker exec mycities-core-laravel php artisan config:clear
```

**Step 6 — Smoke tests (in browser)**
1. `http://localhost/user/splash` — splash page loads, auto-forwards after 3s
2. `http://localhost/user/login` — teal login form shows
3. Login with a test user → redirects to `/user/dashboard`
4. Dashboard shows current date in teal bar
5. `/user/reading/water` → water reading screen with keypad
6. `/user/reading/electricity` → electricity reading screen
7. Admin sidebar `http://localhost/admin/` → "App View" link visible, opens new tab

---

## 20. Compliance Checklist (for senior AI review)

After building, verify each item:

- [ ] `resources/css/user-app.css` exists with all CSS variables
- [ ] `user-app.css` is linked in `app.blade.php`
- [ ] `UserAuthController.php` is in `App\Http\Controllers\User` namespace
- [ ] `UserAppController.php` is in `App\Http\Controllers\User` namespace
- [ ] All routes in `/user` group are in `web.php`
- [ ] `UserAppLayout.vue` uses `var(--ua-primary)` (#009BA4) for header, not blue
- [ ] `NumericKeypad.vue` — water shows `.` key; electricity hides it
- [ ] `MeterDisplay.vue` — water shows `NNNN - NN` format; electricity shows `NNNNNN`
- [ ] Water readings stored as decimal (e.g. `4.50`), displayed as `0004 - 50`
- [ ] Electricity readings stored as integer (e.g. `123327`), displayed as `123327`
- [ ] Dashboard grand total color is `var(--ua-amount)` (#1565C0 deep blue)
- [ ] "First Reading due in N days" pill uses `var(--ua-orange)` (#FF9800)
- [ ] Water section icon/accent: `var(--ua-water)` (#2196F3)
- [ ] Electricity section icon/accent: `var(--ua-electricity)` (#FFA000)
- [ ] CANCEL button: outline teal; ENTER button: filled teal
- [ ] Admin sidebar "App View" item opens in `target="_blank"` new tab
- [ ] No `window.route()` calls anywhere — use `import { route } from 'ziggy-js'`
- [ ] No `window.route()` calls anywhere in admin files either
- [ ] PHP syntax clean on all new files
- [ ] `npm run build` completes with no errors
- [ ] Docker rebuild done after build

---

*End of specification. Version: 2026-02-20. Designer reference: image `image-9060e81a-7bca-44e9-96ae-6f3867d6cf71.png`.*
