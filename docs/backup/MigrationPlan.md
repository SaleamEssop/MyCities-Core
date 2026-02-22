# MyCities-Core: Laravel + Vue (Inertia) Migration Plan

**Goal:** Single Laravel app with Inertia.js + Vue 3 + Vite. One build, one deploy. Block-Day Calculator as the only billing engine. No separate SPA container.

---

## Phase 1: Foundation (current)

- [x] Create MyCities-Core folder
- [x] ProjectDescription.md with Calculator Implementation Checklist (sanity file)
- [x] Calculator.php skeleton sectioned to PD order
- [ ] **Initialize Laravel 11** with Inertia + Vue 3 + Vite
- [ ] Add Quasar as Vue plugin (retain look/feel)
- [ ] Preserve PD + Calculator after Laravel install

---

## Phase 2: Backend

- [ ] Copy migrations from MyCities-Cline `backend/database/migrations` → MyCities-Core
- [ ] Copy Models (Bill, Meter, Account, etc.) from MyCities-Cline; remove API-only traits/Resources
- [ ] Port Calculator logic from CalculatorPHP (MyCities-Cline) into Calculator.php section by section
- [ ] Replace BillingEngine usage: observers/commands call `Calculator::ensureBillAndCompute` or `computePeriod`
- [ ] Controllers: thin layer calling Calculator; no billing math in controllers

---

## Phase 3: Frontend (Inertia + Vue)

- [ ] Move Vue pages from MyCities-Cline `frontend/src/pages/` → `resources/js/Pages/`
- [ ] Delete Vue Router; all routes in `routes/web.php` (Inertia)
- [ ] Refactor pages to receive data as props (Laravel pushes via Inertia), not onMounted + Axios
- [ ] Create `resources/js/Layouts/AdminLayout.vue` (sidebar, nav)
- [ ] Map routes in web.php to Inertia pages (e.g. `Route::get('/billing', ...)->name('billing.index')`)

---

## Phase 4: Docker & Deploy

- [ ] Single Nginx serving Laravel `public/` (Vite builds into public)
- [ ] Remove separate Vue container; one Laravel container (or Laravel + Nginx + MySQL as today)
- [ ] Update docker-compose and Dockerfile for Laravel + Node (Vite build at build time)

---

## Order of execution (next)

1. **Laravel + Inertia + Vue 3 + Vite** in MyCities-Core (this run)
2. **Quasar** plugin
3. Copy migrations + models
4. Port Calculator logic from CalculatorPHP
5. Copy and adapt Vue pages to Inertia
6. Docker simplification
