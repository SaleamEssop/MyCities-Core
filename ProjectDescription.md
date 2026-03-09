# ProjectDescription.md (PD.md) — High-Level Legal Contract

**Purpose:** Ultimate reference for billing logic. Every section ID maps 1:1 to a method or block in `app/Services/Billing/Calculator.php` (c.php). No implementation may deviate without updating this document.

**Architecture:** MyCities-Core only. Laravel 11. Vue 3 + Quasar (Vite). Inertia.js. PHP 8.3. **All consumption in integers (Litres/Wh)** until final cost step (No-Float Rule).

**Minimum path (source of truth → UI):**
- `Calculator.php` (all billing math, PD Sections 1–12)
- `Calendar.php` (all date math, PD Section 5.0)
- `CalculatorController.php` (HTTP bridge, serves Inertia + JSON endpoints)
- `Calculator.vue` (sole UI, Vue 3 / Inertia)

No Blade views for the calculator. No legacy CalculatorPHP, BillingPeriodCalculator, BillingCalculatorController, or AccountBillingCalculatorController — all deleted.

**Enforcement:** Calculator.vue is UI only. Period and sector logic lives only in Calculator.php / Calendar.php. No exceptions. The build (Build_Core.cmd / BuildDocker_Core.ps1) runs a check before Docker build; if Calculator.vue contains forbidden period/sector-building patterns, the build fails.

---

## Section 0: Field Definitions & State Rules (Reference for c.php)

**Opening Reading:** Previous period `calculated_closing` ?? previous `provisional_closing` ?? first reading ?? meter `start_reading`.  
**Provisional Closing:** Mutable until finalized; then IMMUTABLE (never overwrite).  
**Calculated Closing:** Set only via healing (sector overlap at period end).  
**c.php:** These rules are enforced inside `computePeriod()` and helpers; no separate method.

---

## Section 1.0: The Sequential Gate

**Plain language:** No period may be calculated if the previous period is not reconciled. Period N needs Period N-1 to have a `calculated_closing` (or at least `provisional_closing`) so that N’s opening reading is known. If N-1 is not reconciled, block N and trigger healing of N-1 first (recursively).

**c.php implementation:** `validateSequentialGate(array $bill, array $readings, object $meter): array`  
Returns `['allowed' => bool, 'blocked_reason' => ?string, 'heal_bill_id' => ?int]`. If not allowed, caller must call `computePeriod(heal_bill_id)` then retry.

---

## Section 2.0: Sector Splitting

**Plain language:** Break readings into chronological sectors. Each sector is the span between two consecutive readings (start date, end date, start reading value, end reading value). Usage for that sector = end reading − start reading (integer). Handle meter rollover (e.g. 9999 → 0001) using meter digit count. All units are whole numbers (Litres/Wh).

**c.php implementation:** `generateSectors(array $readings, object $meter): array`  
Returns list of sectors: `[['start' => date, 'end' => date, 'start_reading' => int, 'end_reading' => int, 'total_usage' => int, 'block_days' => int], ...]`. Use inclusive block-day count (see Section 5.0).

---

## Section 3.0: The Straddle Split

**Plain language:** When a sector spans two (or more) billing months, split it at each period boundary. Distribute the sector’s total usage across sub-segments by block-day ratio. Do not use floats for the litre/Wh amounts: use Section 4.0 (Remainder Method) so that the sum of sub-segments exactly equals the sector total.

**c.php implementation:** `handleStraddle(array $sector, array $periodBoundaries, object $calendar): array`  
Returns list of sub-sectors, each with `['start' => date, 'end' => date, 'usage' => int]`. Total of `usage` must equal `$sector['total_usage']`. Uses `applyRemainderMethod()` internally.

---

## Section 4.0: The Remainder Method (No-Float Rule)

**Plain language:** When splitting a total (e.g. 100 L over 3 days), do not use floating point. For sub-segments 1 to N−1, assign `floor(total × (segment_blocks / total_blocks))`. Assign the remainder to the final segment so that the sum of all segments equals the original total exactly. All consumption values in c.php are integers.

**c.php implementation:** `applyRemainderMethod(int $totalUsage, array $segmentBlockCounts): array`  
`$segmentBlockCounts` is e.g. `[12, 28, 10]` (block days per segment). Returns e.g. `[33, 33, 34]` so that 33+33+34 = 100. First N−1: `floor($totalUsage * $segmentBlockCounts[$i] / $totalBlocks)`. Last: `$totalUsage - sum(previous)`.

---

## Section 5.0: Calendar & Inclusive Block Days

**Plain language:** Period boundaries and day counts use a single rule: **inclusive** block days. From start date to end date, block_days = (end − start in days) + 1. SAST timezone for all dates. Leap years and month lengths respected. This logic lives in `calendar.php`, not in c.php. c.php calls calendar for period start/end and for block-day counts when needed.

**c.php / calendar.php:**  
- **calendar.php:** `periodStart(string $date, int $billDay): string`, `periodEnd(string $periodStart, int $billDay): string`, `blockDays(string $start, string $end): int`, `nextDay(string $date): string`. All day math inclusive.  
- **c.php:** Uses `Calendar::blockDays()` (or equivalent) for sector and segment block counts; never implements its own day math.

---

## Section 5.1: Period Enumeration

**Plain language:** Given a bill day and a date range (start → end), enumerate every billing period whose start falls within that range. Both `start` and `end` of each period are inclusive (Calendar semantics). This is used by the calculator UI to display period slots for an account, and by setup validation to confirm there is exactly one active period containing today.

**c.php implementation:** `calculatePeriods(int $billDay, string $startDate, string $endDate): array`  
Returns `[['start' => 'Y-m-d', 'end' => 'Y-m-d'], ...]`. Delegates entirely to `Calendar::periodStart()`, `Calendar::periodEnd()`, and `Calendar::nextDay()`. No billing math here.

---

## Section 5.2: Date-to-Date period enumeration

**Plain language:** Given an anchor (date + litres) and a list of readings (date + litres), build periods for Date-to-Date billing. Accept each reading into the current period; when the last reading is ≥ 30 days (inclusive block days) from the period opening, close that period and open the next. The next period’s opening = previous period’s closing (meter does not reset). Used by the calculator UI (Test and Account mode) and any backend that needs D2D period structure.

**c.php implementation:** `buildD2dPeriodsFromAnchorReadings(string $anchorDate, $anchorLitres, array $readings, ?string $today = null): array`  
Returns a list of period objects (start, end, blockDays, water opening/closing, sectors, etc.). Uses `Calendar::blockDays()`, `Calendar::nextDay()`. Helper `buildD2dSectors()` builds sectors from a reading chain (inclusive block days). All consumption in integers.

---

## Section 6.0: Public Entry Point

**Plain language:** The only public entry for calculation is `computePeriod(int $billId): array`. It loads the bill, meter, and readings; calls `validateSequentialGate`; then `generateSectors`; then for any sector that straddles a period boundary, `handleStraddle` (which uses `applyRemainderMethod`); distributes usage to the bill period; then resolves the tariff template and executes Sections 7.0–11.0 to produce the full monetary bill. Persists usage, charge breakdown, and bill total in a single transaction. Returns a result array (success, message, data).

**c.php implementation:** `computePeriod(int $billId): array`

---

## Section 7.0: Tariff Template Resolution

**Plain language:** Before any charge can be computed, the correct tariff template must be identified for the bill. The template is linked to the meter's account via the `regions_account_type_cost` table. Only one active template may apply for the bill's period end date (`effective_from` ≤ period_end ≤ `effective_to`, `is_active = true`). If no template is found, the calculation must fail with a clear error — never silently produce a zero charge.

**c.php implementation:** `loadTariffTemplate(object $bill, object $meter): object`  
Reads `regions_account_type_cost` via DB. Throws `\RuntimeException` if no active template is found for the period.  
**Data sources:** `regions_account_type_cost` (template), `tariff_tiers` (structured tiers), `tariff_fixed_costs` (fixed lines).

---

## Section 8.0: Tiered Charge Computation (No-Float Rule applies)

**Plain language:** Apply the tariff template's tiered rates to the integer usage computed in Sections 2.0–4.0. Tiers are defined either as a JSON array on the template (`water_in`, `water_out`, `electricity` columns) or as rows in the `tariff_tiers` table. For each tier: charge = units_in_tier × rate_per_unit. All intermediate litre/Wh values remain integers; monetary amounts (rand) may be decimal at this point. The sum of all tier charges is the **usage charge** for the period.

**c.php implementation:** `applyTieredRates(int $usageLitres, array $tiers): array`  
Returns `['usage_charge' => float, 'tier_breakdown' => array]`. Each tier breakdown entry: `['tier' => int, 'units' => int, 'rate' => float, 'amount' => float]`.

---

## Section 9.0: Fixed Costs

**Plain language:** Fixed costs are line items that apply regardless of usage (e.g. service charge, basic fee). They are defined in the `tariff_fixed_costs` table linked to the template, or in the `fixed_costs` JSON column on the template. Each fixed cost has a name, amount, and a flag indicating whether VAT applies to it. Fixed costs are summed separately from usage charge so that VAT can be applied selectively.

**c.php implementation:** `applyFixedCosts(object $template): array`  
Returns `['fixed_total' => float, 'fixed_breakdown' => array]`. Each entry: `['name' => string, 'amount' => float, 'is_vatable' => bool]`.

---

## Section 10.0: Customer Cost Overrides

**Plain language:** A customer may have negotiated rates or additional charges that override or supplement the template defaults. Overrides are stored either in the `customer_costs` JSON column on the template, or in the `customer_cost_overrides` table linked to the account. Customer overrides are applied after fixed costs and before VAT. If no overrides exist, this step produces zero and is skipped silently.

**c.php implementation:** `applyCustomerOverrides(object $bill, object $template): array`  
Returns `['override_total' => float, 'override_breakdown' => array]`.

---

## Section 11.0: VAT

**Plain language:** VAT is applied at the rate defined on the tariff template (`vat_rate` column, expressed as a percentage, e.g. 15.0). VAT applies to: usage charge (Section 8.0), vatable fixed costs (Section 9.0), and vatable customer overrides (Section 10.0). Non-vatable fixed costs are excluded. VAT is computed on the vatable subtotal, never on the grand total, to avoid double-counting. All monetary values are rounded to 2 decimal places only at the final persistence step.

**c.php implementation:** `computeVat(float $vatableAmount, float $vatRate): float`  
Returns VAT amount. Caller sums: `bill_total = usage_charge + fixed_total + override_total + vat_amount`.

---

## Section 12.0: Persistence — Bill Total & Breakdown

**Plain language:** After all charge components are computed, write them back to the `bills` table in a single update within the transaction opened by `computePeriod()`. The following columns must be written: `consumption` (integer Litres/Wh), `usage_charge`, `fixed_charge`, `override_charge`, `vat_amount`, `bill_total`. A JSON `breakdown` column stores the full tier-by-tier and fixed-cost detail for audit purposes. Once written, the bill moves to status `calculated`.

**c.php implementation:** `persistBill(int $billId, int $usageL, array $chargeResult): void`  
Extends (replaces) the existing `persistBillUsage()` helper. Writes all monetary columns and the breakdown JSON atomically.

---

## Tariff Dependency Map

| Step | What c.php requires | DB table |
|---|---|---|
| Load tariff template | Template linked to meter/account, active for period | `regions_account_type_cost` |
| Apply tiered rates | Tier definitions (JSON or rows) | `regions_account_type_cost` + `tariff_tiers` |
| Apply fixed costs | Fixed cost line items | `tariff_fixed_costs` |
| Apply customer overrides | Per-account overrides | `customer_cost_overrides` |
| Apply VAT | `vat_rate` on template | `regions_account_type_cost` |
| Write bill total | `consumption`, charges, breakdown, total | `bills` |

All reads use the existing `DB` facade already present in c.php. No new application-level dependencies are introduced.

---

## Mirror Summary (PD Section → c.php)

| PD Section | Plain language (short)           | c.php |
|------------|-----------------------------------|-------|
| 0          | Field definitions & state rules   | Enforced in `computePeriod` and helpers |
| 1.0        | Sequential Gate                   | `validateSequentialGate()` |
| 2.0        | Sector splitting                  | `generateSectors()` |
| 3.0        | Straddle split                    | `handleStraddle()` |
| 4.0        | Remainder Method (no float)       | `applyRemainderMethod()` |
| 5.0        | Calendar / inclusive days         | `calendar.php` |
| 5.1        | Period enumeration                | `calculatePeriods()` |
| 5.2        | Date-to-Date period enumeration   | `buildD2dPeriodsFromAnchorReadings()`, `buildD2dSectors()` |
| 6.0        | Public entry                      | `computePeriod()` |
| 7.0        | Tariff template resolution        | `loadTariffTemplate()` |
| 8.0        | Tiered charge computation         | `applyTieredRates()` |
| 9.0        | Fixed costs                       | `applyFixedCosts()` |
| 10.0       | Customer overrides                | `applyCustomerOverrides()` |
| 11.0       | VAT                               | `computeVat()` |
| 12.0       | Persist bill total & breakdown    | `persistBill()` |

---

**Last updated:** 2026-03-01  
**Enforcement:** c.php must implement exactly these methods and no other billing math. All consumption in integers. Monetary rounding (2 dp) only at Section 12.0 persistence. No CalculatorPHP, no BillingPeriodCalculator, no Blade calculator views.
