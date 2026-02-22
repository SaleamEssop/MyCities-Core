# Critical Fix Procedures

Documented fixes for non-obvious bugs that are easy to re-introduce.

---

## FIX-001 — Inertia App Renders Blank / `TypeError: Cannot read properties of undefined (reading 'url')`

**Date:** 2026-02-21  
**Status:** Fixed  
**Affected file:** `resources/js/inertia-app.js`

### Symptom

Browser console shows:
```
TypeError: Cannot read properties of undefined (reading 'url')
    at Ql.handleInitialPageVisit (inertia-app-CstOJuaN.js:6:8563)
    at Ql.init (inertia-app-CstOJuaN.js:6:8048)
    at setup (inertia-app-CstOJuaN.js:77:23466)
```

The Inertia page renders a blank screen. The error occurs on every Inertia route (e.g. `/admin/calculator`). Non-Inertia Blade routes (e.g. `/admin/billing-calculator`) are unaffected.

### Root Cause

`@inertiajs/vue3` v1.x requires the `App` component to receive `props` (which contains `initialPage` and `resolveComponent`) via Vue's `h()` render function.

Without `h(App, props)`, the Inertia `App` component's internal `setup()` is called with no `initialPage`. It then calls `router.init({ initialPage: undefined })`, which calls `handleInitialPageVisit(undefined)`, which throws when it accesses `undefined.url`.

### Wrong Code

```js
// ❌ WRONG — props are never passed to App
setup({ el, App, props, plugin }) {
    createApp(App).use(plugin).mount(el);
}
```

### Correct Code

```js
// ✅ CORRECT — props passed via h() so App receives initialPage
setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
}
```

### Full Fixed `inertia-app.js`

```js
import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    resolve: async (name) => {
        const path = `./Pages/${name}.vue`;
        const loader = pages[path];
        if (!loader) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        return (await loader()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    title: (title) => (title ? `${title} - MyCities-Core` : 'MyCities-Core'),
});
```

### Why It Is Easy To Re-Introduce

- The `createApp(App)` syntax is valid Vue 3 and produces no error at build time.
- Many older Inertia v0.x examples and outdated documentation use `createApp(App)`.
- AI assistants and code generators frequently produce the wrong form because `createApp(App)` looks correct.
- The v1.x requirement for `h(App, props)` is only documented in the official Inertia upgrade guide.

### Verification

After applying the fix, run:
```bash
npm run build
```
Then navigate to any Inertia route (e.g. `http://localhost/admin/calculator`). The page should render with no console errors.

---

## FIX-002 — 502 Bad Gateway on First Container Start

**Date:** 2026-02-21  
**Status:** Fixed  
**Affected files:** `infrastructure/docker-compose.yml`, `Dockerfile`

### Symptom

Browser shows `502 Bad Gateway` immediately after `docker-compose up -d`. Refreshing after ~30 seconds resolves it.

Nginx logs show:
```
connect() failed (111: Connection refused) while connecting to upstream,
upstream: "fastcgi://172.x.x.x:9000"
```

### Root Cause

Two issues combined:

1. **Startup race:** The Laravel health-check used `php-fpm -t` (config syntax test only). This passes before PHP-FPM actually starts listening on port 9000. Nginx starts, sees the container as "healthy", and immediately forwards requests — but PHP-FPM is not yet accepting connections.

2. **Missing APP_KEY:** The Dockerfile entrypoint wrote `APP_KEY=` (blank) instead of `APP_KEY=${APP_KEY:-}`. Laravel requires a valid APP_KEY to encrypt sessions. Without it, `php artisan key:generate --force` fails silently because the `.env` file has no `APP_KEY=` line with a value to write to.

### Fixes Applied

**`infrastructure/docker-compose.yml` — health-check now verifies port 9000 is live:**
```yaml
healthcheck:
  test: ["CMD-SHELL", "php-fpm -t 2>/dev/null && (echo '' | timeout 2 bash -c 'cat < /dev/null > /dev/tcp/127.0.0.1/9000') 2>/dev/null || exit 1"]
  interval: 10s
  timeout: 8s
  retries: 6
  start_period: 40s
```

**`Dockerfile` — APP_KEY written from environment variable:**
```dockerfile
# Before (wrong):
APP_KEY=\n\

# After (correct):
APP_KEY=${APP_KEY:-}\n\
```

**`Dockerfile` — key:generate only runs when APP_KEY is still blank:**
```sh
if grep -q "^APP_KEY=$" /var/www/html/.env 2>/dev/null; then
    php artisan key:generate --force 2>/dev/null || true
fi
```

### Verification

After `docker-compose up -d`, wait for all containers to show `(healthy)`. The first request to `http://localhost/admin` should return 302 (redirect to login) with no 502.

---

## FIX-003 — `region_cost` Page Returns 500

**Date:** 2026-02-21  
**Status:** Fixed  
**Affected file:** `app/Http/Controllers/RegionsCostController.php`

### Symptom

`http://localhost/admin/region_cost` returns HTTP 500. Laravel log shows:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'account_type_id' in 'order clause'
(SQL: select * from `regions_account_type_cost` order by `region_id` asc, `account_type_id` asc)
```

### Root Cause

`RegionsCostController::index()` ordered by `account_type_id`, but this column does not exist in the `regions_account_type_cost` table.

### Fix

```php
// Before (wrong):
$costs = RegionsAccountTypeCost::with(['region', 'accountType'])
    ->orderBy('region_id')
    ->orderBy('account_type_id')
    ->get();

// After (correct):
$costs = RegionsAccountTypeCost::with(['region'])
    ->orderBy('region_id')
    ->orderBy('id')
    ->get();
```

---

## FIX-004 — `/admin` Route Shows Blank Inertia Screen After Login

**Date:** 2026-02-21  
**Status:** Fixed  
**Affected file:** `routes/web.php`, `app/Http/Controllers/AdminController.php`

### Symptom

After login, `http://localhost/admin` shows a blank screen with:
```
Error: Inertia page not found: Admin/Dashboard
```

### Root Cause

The `/admin` route was configured to use `Inertia::render('Admin/Dashboard')`, but the admin dashboard is a full Bootstrap/Blade page, not an Inertia Vue component. The `Admin/Dashboard.vue` component was a minimal placeholder with no real content.

### Fix

Changed `/admin` to use the real Blade dashboard:

```php
// routes/web.php — before (wrong):
Route::get('/', function () {
    return Inertia::render('Admin/Dashboard');
});

// routes/web.php — after (correct):
Route::get('/', [AdminController::class, 'home'])->name('admin.home');
```

```php
// AdminController.php — added:
public function home() { return view('admin.home'); }
```

---

## FIX-005 — `route is not a function` / `undefined 'route'` in Vue Templates (Ziggy not installed)

**Date:** 2026-02-21  
**Status:** Fixed  
**Affected files:** `resources/js/inertia-app.js`, `resources/views/app.blade.php`, `composer.json`, `package.json`

### Symptom

Browser console shows on any Inertia page that calls `route()` in a template (e.g. Login):
```
TypeError: n.route is not a function           (first occurrence)
TypeError: Cannot read properties of undefined (reading 'route')  (after partial fix attempts)
```
The page crashes during render. Inertia pages that do not call `route()` in their template are unaffected. **This is NOT the same as FIX-001.**

### Root Cause

**`tightenco/ziggy` (PHP) and `ziggy-js` (npm) were not installed at all.**

- `@routes` Blade directive — sets `window.Ziggy` (JSON route definitions). It does **NOT** set `window.route`.
- `route()` function — provided only by the `ziggy-js` npm package (specifically the `ZiggyVue` plugin).
- Without either package, the `@routes` directive is unregistered (Blade error), and `route()` simply does not exist.

Additionally, hardcoded Vite asset hashes in `app.blade.php` (from a previous AI change) caused stale asset references after every build.

### Wrong State

`composer.json` — missing:
```
"tightenco/ziggy": "^2.6"
```

`package.json` — missing:
```
"ziggy-js": "^2.6.1"
```

`app.blade.php` — hardcoded hash (breaks after every build):
```html
<script type="module" src="http://localhost/build/assets/inertia-app-D6b_a4A6.js"></script>
```

### Correct Code

`composer.json` — add to `require`:
```json
"tightenco/ziggy": "^2.6"
```

`package.json` — add to dependencies:
```json
"ziggy-js": "^2.6.1"
```

`inertia-app.js` — import and use ZiggyVue plugin:
```js
import { ZiggyVue } from 'ziggy-js';
// ...
const app = createApp({ render: () => h(App, props) });
app.use(plugin).use(ZiggyVue).mount(el);
```

`app.blade.php` — use the vite() helper, never hardcode hashes:
```html
@routes
{!! vite(['resources/js/inertia-app.js']) !!}
```

### How ZiggyVue Works

1. `@routes` in `app.blade.php` → Outputs an inline `<script>` that sets `window.Ziggy = { routes: {...}, url: '...' }`.
2. `ZiggyVue` plugin reads `window.Ziggy` on mount and makes `route('name', params)` available in all Vue templates.
3. No manual `globalProperties.route` assignment needed — `ZiggyVue` handles this.

### Verification

After fix + `npm run build` + `php artisan view:clear`: visiting `/admin/login` renders the login form without console errors. The `Link :href="route('admin.forgot-password')"` in `Login.vue` resolves correctly.

### Prevention

- **Never hardcode Vite asset filenames** in Blade — use `{!! vite([...]) !!}` exclusively.
- Always install both `tightenco/ziggy` (PHP) AND `ziggy-js` (npm) together.
- Register `ZiggyVue` with `.use(ZiggyVue)` in `inertia-app.js` — do not attempt manual `window.route` wiring.

---

## RISK-001 — BillingEngine.php is a Stub (Silent Zero Risk)

**Status:** Open — needs audit before production

**File:** `app/Services/BillingEngine.php`

**Symptom if triggered:** Billing calculations silently return zero / false with no error thrown.

**Root cause:**
`BillingEngine.php` contains only stub implementations:

```php
class BillingEngine
{
    public function calculateCharge($a,$b,$c){ return (object)['tieredCharge'=>0]; }
    public function process($a,$p){ return ['success'=>false]; }
    public function reconcileProvisionalPeriods($a,$r){ return ['success'=>false]; }
}
```

**Referenced by 8+ files:**
- `app/Console/Commands/BackfillMissingBills.php`
- `app/Console/Commands/DiagnoseMissingBills.php`
- `app/Console/Commands/GenerateHistoricalBills.php`
- `app/Console/Commands/RegenerateBillsWithBreakdown.php`
- `app/Http/Controllers/BillController.php`
- `app/Http/Controllers/Admin/BillingCalculatorController.php`
- `app/Http/Controllers/Admin/UserAccountManagerController.php`
- `app/Http/Controllers/Api/AdminReadingsController.php`

**Required audit:**
For each file above, check whether it calls `BillingEngine` methods and uses their return values for billing math.
- If yes → those call sites must be redirected to `app/Services/Billing/Calculator.php`
- If no (imported but not called, or result ignored) → `BillingEngine` can be safely deleted

**Do NOT delete `BillingEngine.php` until the audit is complete.**
