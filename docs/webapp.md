# webapp.md — Architecture Notes for the Mobile-Optimised App

**Purpose:** Living reference document capturing all architectural decisions, billing model
rules, and implementation notes agreed during development. Updated as decisions evolve.
Do NOT deviate from the rules below without updating this document.

**Related documents:**
- `ProjectDescription.md` (PD.md) — billing legal contract, the ultimate authority on billing math
- `docs/UserAppImplementationSpec.md` — complete build guide for the user-facing app
- `docs/DevelopmentGuidelines.md` — code change rules and duplicate-prevention checklist

---

## 1. Billing Lifecycle Model

Three rules govern how bills are computed, stored, and served.

### Rule 1 — Snapshot Past (trigger: bill day boundary crossed)

When the bill day arrives and a new period is created, the billing engine runs on the
closing period:

1. The Sequential Gate (PD §1.0) is checked — all prior periods must have a snapshot.
2. Any prior periods that can now be resolved are healed forward (see Rule 3).
3. The closing period is computed, persisted to the `bills` table, and **locked**.
4. Once locked, a past bill is immutable. It is never recomputed.

### Rule 2 — Live Current (trigger: new reading entered)

The current (open) period is the only period that stays "live." Every time a new reading
is entered against any meter on the account, the current period bill is recomputed and
its snapshot is updated (it is not yet locked).

### Rule 3 — No Recomputation of Past Bills

Once a period is snapshotted (locked), serving it to any client is a pure DB read. The
billing engine is never invoked for past periods. This eliminates heavy computation on
account open and makes historical data instant.

```
Bill Day arrives
    │
    ▼
Engine: run sequential gate, resolve any gaps, snapshot closing period (lock it)
    │
    ▼
New open period begins (live, recomputes on readings)
    │
    ▼
Client opens account → past periods = DB read, current = latest computation
```

---

## 2. Bootstrap Phase vs Steady State

### Bootstrap (occurs exactly once per meter)

From meter initialization to the **first actual reading after initialization**, the system
is in bootstrap. During this window:

- A period with only the initialization reading (one data point) **cannot be computed**.
- The UI shows: "Unable to compute. A minimum of two readings are required."
- The period is snapshotted at bill day with zero usage and a note that it was
  unresolvable at the time.
- The snapshot is never touched again.

### Steady State (all periods after the first actual reading)

Once two readings exist, a consumption rate is established. From this point:

- Every subsequent period has a known opening (previous close) and an inherited daily
  usage rate.
- **An unresolvable period can never occur again.** Even if no new reading arrives,
  the system projects forward indefinitely using the inherited rate.
- When a new reading does arrive, the rate is updated and future projections self-correct.

---

## 3. Healing Rule — Correct Forward, Never Backward

When a reading in Period N+1 retroactively reveals consumption for Period N (which had
been snapshotted as zero/unresolvable):

- **Period N's snapshot is never modified.** It is a locked record.
- The correction flows **forward** as an **Adjustment b/f** line item inside Period N+1's
  bill.
- PD §3.0 (Straddle Split) and §4.0 (Remainder Method) govern how the consumption is
  attributed across the period boundary.

### Straddle Split example

```
Initialization: Aug 15, 0001.00 kL (1 000 L)
First reading:  Sep 15, 0040.33 kL (40 325 L)

Sector: Aug 15 → Sep 15, 39 325 L, 32 inclusive block days

Straddle at boundary Sep 14 / Sep 15:
  Period 1 sub-segment: Aug 15 → Sep 14, 31 days → floor(39 325 × 31 / 32) = 38 119 L
  Period 2 sub-segment: Sep 15 → Sep 15,  1 day  → 39 325 − 38 119             = 1 206 L

Period 1: snapshotted as zero (bootstrap). Adjustment b/f of 38 119 L carried into Period 2.
Period 2: opens at Period 1 calculated closing (39 119 L), Sep 15 reading is a line item.
```

---

## 4. Sequential Gate (PD §1.0)

No period may be calculated if the previous period is not yet resolved. Period N requires
Period N-1 to have a `calculated_closing` (or at least `provisional_closing`) so that
Period N's opening is known.

Enforced in the admin Calculator (`calcBlockReason()` in `Calculator.vue`):

```javascript
if (pi > 0) {
  const prev = activePeriods.value[pi - 1]
  if (prev && !prev.bill) return `Period ${pi} must be calculated before this period`
}
```

The backend billing engine (`computePeriod()` in `Calculator.php`) enforces the same rule
via `validateSequentialGate()`.

---

## 5. Block-Day Model Reference

- **Inclusive counting:** `block_days = (end_date − start_date in days) + 1`
  Both the start day and end day are counted as full billing days.
- **Period boundary:** Bill day = first day of new period. Period end = bill day − 1.
  Example: bill day 15 → Period 1: Aug 15 → Sep 14 (31 days), Period 2: Sep 15 → Oct 14 (30 days).
- **Sector anchor rule:** The opening anchor date must NOT advance to `period.end` when
  a period produces no provisional close (insufficient data). The original anchor date is
  preserved so the next period that receives a reading can measure the correct span.
  Fixed in `reconstructPeriods()` in `Calculator.vue`:
  ```javascript
  date: provisionalClosing != null ? end : opening.date,
  ```

---

## 6. Read Day and Alarm System

### Read Day

- **Read Day = bill day − 5 days** (= `period.end − 4 days`).
- This is the recommended day for the account holder to submit a meter reading, giving
  the system 5 days to process before the bill is generated.

### Countdown

- A countdown strip is shown in the current period panel starting **5 days before
  Read Day**.
- Colour states: amber (approaching), red (today), red (overdue).

### Alarms (stored in `alarm_definitions` table)

| Code    | Name              | Condition                                                         | Delivery |
|---------|-------------------|-------------------------------------------------------------------|----------|
| ALM-001 | No Period Reading  | No readings in current period AND period start > 5 days ago      | Modal    |
| ALM-002 | Reading Overdue   | Readings exist but last read was > 5 days ago                     | Modal    |

Both alarms fire only on the current (last / open) period. They respect the test-mode
current date override (`effectiveToday` in `Calculator.vue`).

---

## 7. Mobile App Architecture Notes

### Stack

- Laravel 11, PHP 8.3, Vue 3 (`<script setup>`), Inertia.js v1.x, Vite
- Identical stack to the admin panel — shared blade root, shared auth guard
- Full spec in `docs/UserAppImplementationSpec.md`

### UI Constraints

- Phone-width container: `max-width: 414px`, centred on desktop
- Fixed bottom navigation bar (Home, Dashboard, Readings, Account)
- Teal header (`#009BA4`), Nunito font
- CSS design tokens in `resources/css/user-app.css`

### Data Flow

| Scenario | What happens |
|----------|-------------|
| User opens account | Past periods: pure DB read (snapshots). Current period: latest computation served. No engine run. |
| User enters a reading | Current period recomputed immediately. Bill updated. |
| Bill day boundary crossed | Server-side scheduled job (or on-open detection) runs the billing engine. Closing period snapshotted and locked. New period opens. |
| User scrolls to older bill | DB read — instant. No computation. |

### Billing Engine Trigger

The billing engine fires when the bill day boundary is crossed. Two mechanisms:

1. **Scheduled job (preferred):** A cron/queue job runs nightly, finds accounts whose bill
   day has passed without a snapshot, and runs `computePeriod()` for each.
2. **On-open detection (fallback):** When a user opens their account, the server checks if
   an expected period snapshot is missing and runs the engine before returning data.

### Reading Entry Rules

- Water: stored as decimal litres (e.g. `40325` = 40.325 kL), displayed as `0040.33 kL`
- Electricity: stored as integer Wh, displayed as 6-digit kWh (e.g. `123456`)
- A reading must be greater than the previous reading (meter cannot go backwards)
- Reading type: `ACTUAL` (user-submitted), `CALCULATED` (system-derived), `PROVISIONAL` (projected)
- Only `ACTUAL` readings are shown as line items in the period Readings section
- `CALCULATED` and `PROVISIONAL` readings are used for boundary calculations only

---

## 8. Admin Calculator — Key Behaviours

These are implemented in `resources/js/Pages/Admin/Calculator.vue`:

| Feature | Location | Behaviour |
|---------|----------|-----------|
| Sequential Gate | `calcBlockReason()` | Blocks Calculate button if previous period has no bill |
| Insufficient data notice | Period water/electricity template | Amber banner when only one reading exists |
| Read Day countdown | Stats bar area, current period only | Shows days-to/overdue strip |
| Test mode date override | Setup section | Date input + Active/Inactive toggle, constrained to last period start → today |
| Account mode reconstruction | `reconstructPeriods()` | Chained openings; readings assigned to correct period; anchor date preserved |
| Sector building | `buildSectors()` | First sector start = opening date (inclusive). Subsequent = nextDay(prev reading) |

---

## 9. Open Questions / Future Work

- [ ] Backend `computePeriod()` implementation of straddle split (PD §3.0) and Adjustment b/f
- [ ] Scheduled job for automatic bill day snapshotting
- [ ] Push notifications for Read Day (ALM-002 via SMS/email as well as modal)
- [ ] Multi-account support in mobile app (account switcher)
- [ ] Payment integration

---

**Last updated:** 2026-02-20
**Maintained by:** Development team — update this file whenever an architectural decision changes.
