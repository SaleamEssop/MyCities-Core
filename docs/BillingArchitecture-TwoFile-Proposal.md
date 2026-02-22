# Billing Architecture Proposal: Single-File Calculator

**Document type:** Technical proposal for review  
**Status:** Draft for AI/human review (simplified: simulation removed, one file)  
**Last updated:** 2026-02-20  
**Context:** MyCities-Cline migration to unified Laravel/Inertia stack; Block-Day model from ProjectDescription.md and CalculatorPHP as sources of truth.

---

## 1. Purpose and Scope

This document proposes a minimal billing architecture for the MyCities system:

- **One core PHP file** contains all billing logic: **Calculator.php**. It includes both period-boundary logic (when does a period start/end?) and the Block-Day engine (usage, healing, sectors, straddle, distribution).
- **Calculator.php** is the single entry point: it owns “compute an existing bill,” “ensure a bill exists and compute it,” and “what is the period for this date?”.
- **Simulation / what-if is not part of this design.** Validation and demos use a test user: create test account/meter/readings, run Calculator, inspect results. Simulation can be added later if needed.
- **ProjectDescription.md** remains the canonical specification for the Block-Day model.

The proposal is intended for review before implementation (e.g. in a new MyCities-Core or refactored codebase).

---

## 2. Sources of Truth (Unchanged)

| Asset | Role |
|-------|------|
| **ProjectDescription.md** | Canonical specification: Block-Day model, Sequential Gate, Straddle Split, Remainder Method, Baton Pass, integer litres, inclusive days, SAST, immutability rules. No code—reference only. |
| **CalculatorPHP.php** (current) | Current implementation. To be ported into **Calculator.php** (engine + period boundaries in one file). |

No other file may implement usage calculation, healing, straddle/sector distribution, or period-boundary logic; only Calculator.php.

---

## 3. Single-File Layout: `Calculator.php`

**Location:** `app/Services/Billing/Calculator.php` (or equivalent).

**Responsibility:** Single source of truth for (1) how billing periods are calculated (calendar) and (2) all usage and healing logic (Block-Day engine). One file, one class.

### 3.1 Period boundaries (inside Calculator.php)

Ported from BillingPeriodCalculator. Answer: “What are the start and end dates of the billing period for a given date and bill day?” No usage math—calendar only.

**Methods (public or internal as needed):**

- `findPeriodStartForDate($date, $billDay): string`
- `calculatePeriodEnd($periodStart, $billDay): string`
- `findPeriodForDate($date, $billDay): array`
- `calculatePeriods(...)` (list of periods for a range; signature as in current BillingPeriodCalculator)

**Rules:** Leap-year and month-length aware; SAST/timezone as per project (e.g. Africa/Johannesburg). Same inclusive/exclusive rules as ProjectDescription.

Used by `ensureBillAndCompute` (to find/create the bill for a period), and by controllers/observers that need “what period is this?”.

### 3.2 Block-Day engine (inside Calculator.php)

Ported from CalculatorPHP. Implements ProjectDescription.md.

- Sequential Gate: Period N cannot complete until Period N-1 is reconciled; recursive healing.
- Sector Engine: Build sectors from readings; rollover handling.
- Straddle Splitter: Split sectors at period boundaries; Remainder Method (integer litres, no drift).
- Distribution: Ratio apportionment, weighted period average for projections.
- Immutability: No changes to provisional_closing for finalized/invoiced periods; only calculated_closing may be updated during healing.

### 3.3 Public API

1. **`computePeriod(int $billId)`**  
   The bill row already exists. Load bill, meter, readings; run gate/sectors/straddle/distribution; persist usage, opening/closing, status. Returns result structure. Use case: admin “recalculate,” API, tests.

2. **`ensureBillAndCompute($meterId, $periodStart, $periodEnd [, $accountId])`** (or equivalent)  
   For the given meter and period, ensure a bill record exists (create if missing); then call `computePeriod($billId)` and return the result. Use case: MeterReadingObserver, backfill/regenerate commands.

3. **Period-boundary methods** (as above) for callers that need period start/end or a list of periods. Can be the same methods used internally by `ensureBillAndCompute`.

**Dependencies:** Database (Bill, Meter, Readings, existing models). No other billing service.

**What does not go here:**

- No simulation / what-if methods. Validation = test user + real Calculator; simulation can be added later.
- Tariff/charge logic that is not part of current CalculatorPHP stays elsewhere or is integrated only where ProjectDescription specifies.
- Controllers, observers, and commands do not contain billing math; they call Calculator.php.

---

## 4. Replacement of Legacy BillingEngine

- **Do not port** BillingEngine.
- **Replace** with: `Calculator::ensureBillAndCompute(...)` and `Calculator::computePeriod($billId)`.
- Observers, commands, and controllers **only** call Calculator.php. They do not create bills except when calling `ensureBillAndCompute` or when admin explicitly creates a draft then calls `computePeriod`.

---

## 5. ProjectDescription.md Update

Add a short section describing the single billing module, e.g. **Section 0.6: Billing Calculator (Calculator.php)**.

**Suggested content:**

- **Purpose:** Single class for (1) how billing periods are defined (period start/end from date and bill day; leap-year aware) and (2) the Block-Day engine (usage, healing, sectors, straddle, distribution).
- **Public entry points:** `computePeriod($billId)`, `ensureBillAndCompute(...)`, and period-boundary methods. No simulation in scope; validation uses a test user and real calculation. Simulation can be added later if required.

---

## 6. Summary of Responsibilities

| Concern | Owner |
|--------|--------|
| Period start/end for a date and bill day | Calculator.php (inside same file) |
| Usage calculation, healing, straddle, remainder method, sequential gate | Calculator.php |
| Create/find bill for a meter/period, then compute | Calculator.php (ensureBillAndCompute) |
| Triggering calculation on reading save, backfill, regenerate | Observers/commands/controllers → call Calculator.php |
| Canonical specification | ProjectDescription.md |
| Simulation / what-if | Out of scope; add later if needed. Use test user for validation. |

---

## 7. Recommended Implementation Plan

1. **Create `Calculator.php`** (or rename CalculatorPHP.php)
   - Port period-boundary logic from BillingPeriodCalculator into the same file (same class or clearly grouped).
   - Keep `computePeriod($billId)`.
   - Add `ensureBillAndCompute($meterId, $periodStart, $periodEnd, $accountId = null)`.
   - Port only the core calculation methods from CalculatorPHP (gate, sectors, straddle, distribution). Do not port any simulation/period-to-period simulation methods; those are out of scope.

2. **Remove / do not implement**
   - WhatIfSimulationService (or refactor it out of scope for this phase). No parity tests for simulation.
   - No second file for periods or simulation.

3. **Update ProjectDescription.md**
   - Add Section 0.6 describing Calculator.php (period boundaries + engine in one file; no simulation).

4. **Testing**
   - Golden scenarios from ProjectDescription run against `Calculator::computePeriod` (and ensureBillAndCompute where relevant). Use test user and real DB rows for validation; no simulation path.

---

## 8. Open Points for Review

1. **Signature of `ensureBillAndCompute`:** e.g. `$meterId`, `$periodStart`, `$periodEnd`, `$accountId = null`. Single period only, or support “ensure all periods in range”?
2. **Tariff/charge logic:** If not in CalculatorPHP, keep in a separate layer that consumes Calculator results.

---

## 9. Checklist for Implementing Reviewer

- [ ] One file: Calculator.php contains period-boundary methods and the full Block-Day engine.
- [ ] Calculator.php has two main entry points: `computePeriod($billId)` and `ensureBillAndCompute(...)`; plus period-boundary methods for callers.
- [ ] No usage or healing logic exists outside Calculator.php.
- [ ] BillingEngine is not ported; all its call sites use Calculator.php.
- [ ] No simulation / WhatIfSimulationService in scope; validation via test user + Calculator.
- [ ] ProjectDescription.md includes Section 0.6 describing the single Calculator.php module.
- [ ] Observers/commands that need “this period calculated” call Calculator::ensureBillAndCompute or computePeriod.

---

**End of proposal.**
