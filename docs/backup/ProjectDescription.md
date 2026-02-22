# MyCities - Billing System Project Description

## Section 0: The Block-Day Reconciliation Engine (Source of Truth)

### A. Field Definitions & Lifecycle

**1. Start Reading (Genesis):**
- **Visibility:** Visible ONLY in Period 1
- **Purpose:** The absolute 0-point for the meter
- **Storage:** Stored on the `meters` table with a date
- **Mutability:** Set once at meter initialization, never changes

**2. Opening Reading:**
- **Purpose:** The baseline for the current period
- **Initial Value:** Equals the previous period's `calculated_closing` if it exists
- **Fallback:** Falls back to previous period's `provisional_closing` if no calculated value
- **Period 1:** Uses the first physical reading or `start_reading` from meter
- **Mutability:** Updates dynamically when previous period's `calculated_closing` changes

**3. Closing (Provisional):**
- **Active Period (status = 'DRAFT', 'PROVISIONAL', 'CALCULATED'):** 
  - ✅ **MUTABLE** - Updates dynamically as you add readings or change projections
  - Moves with each new reading entered
- **Finalized Period (status = 'FINALIZED', 'INVOICED'):**
  - 🔒 **IMMUTABLE** - Frozen for audit integrity
  - Once the month is closed/invoiced, this value is LOCKED
  - **NEVER** overwrite this value after finalization

**4. Closing (Calculated):**
- **Initial State:** Null/Empty
- **When Populated:** Only when a later reading (in a future period) provides the "Physical Truth"
- **Method:** Calculated via Ratio Apportionment from overlapping sectors
- **Purpose:** Represents the "Healed Truth" - what the closing actually was based on later readings

**5. Projected Usage:**
- **Formula:** `Latest Reading/Provisional Closing - Opening Reading`
- **Updates:** In real-time as readings are added in active periods

### B. The "Healing" Workflow (The Jan/Feb Rule)

**Step 1: January (Active)**
- You enter readings throughout the month
- `Closing (Provisional)` moves from 35,000 → 38,750 as readings are added
- This value is MUTABLE and updates with each new reading

**Step 2: January (Finalized)**
- You lock/close the period
- `Closing (Provisional)` is now FROZEN at 38,750
- Status changes to 'FINALIZED' or 'INVOICED'
- This value can NEVER be changed (audit integrity)

**Step 3: February (Active)**
- Starts with `Opening Reading` = 38,750 (from Jan's Provisional Closing)
- This is the "seed" value until healing occurs

**Step 4: February (Reading Received)**
- You enter 55,000 on Feb 10th
- The Sector Engine creates a sector from Jan 20 to Feb 10
- It calculates that Jan actually ended at 41,000 (via Ratio Apportionment)

**Step 5: Healing Occurs**
- Jan `Closing (Calculated)` is filled with 41,000
- Feb `Opening Reading` "Heals" and updates to 41,000
- Jan `Closing (Provisional)` remains 38,750 (IMMUTABLE - never changed!)
- Variance of +2,250 L is displayed for reconciliation

### C. State Change Rules (MANDATORY)

```php
// RULE: Provisional Closing Immutability
if ($bill->status === 'FINALIZED' || $bill->status === 'INVOICED') {
    // NEVER update provisional_closing
    // This is locked for audit integrity
    return; // or throw exception
}

// RULE: Opening Reading Source
$openingReading = $prevBill->calculated_closing 
    ?? $prevBill->provisional_closing 
    ?? $firstReading 
    ?? $meter->start_reading;

// RULE: Calculated Closing Only via Healing
// Only populate calculated_closing when a FUTURE period has readings
// that overlap this period's end date
```

### D. The Reconciliation Flow (Visual)

```
JANUARY (Finalized):
┌─────────────────────────────────────────────────────────┐
│ Status: FINALIZED                                       │
│ Provisional Closing: 38,750 L (🔒 LOCKED - Immutable)   │
│ Calculated Closing: 41,000 L (Healed from Feb reading)  │
│ Variance: +2,250 L                                      │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼ (Baton Pass - Opening syncs to Calculated)
FEBRUARY (Active):
┌─────────────────────────────────────────────────────────┐
│ Status: CALCULATED                                      │
│ Opening Reading: 41,000 L (Synced to Jan's Calculated)  │
│ New Reading (Feb 10): 55,000 L                          │
│ Projected Usage: 55,000 - 41,000 = 14,000 L             │
│ Provisional Closing: ~55,000 L (✏️ MUTABLE)             │
└─────────────────────────────────────────────────────────┘
```

### Ratio Apportionment Formula

When a sector spans multiple periods, usage is distributed proportionally:

```
Usage = Total_Sector_Units × (Overlap_Blocks / Total_Sector_Blocks)

Example:
- Sector: Jan 25 to Feb 10 = 17 blocks (inclusive)
- Total Usage: 15,000 L
- January overlap: Jan 25-31 = 7 blocks
- February overlap: Feb 1-10 = 10 blocks

January's share: 15,000 × (7/17) = 6,176 L
February's share: 15,000 × (10/17) = 8,824 L
```

---

## Section 0.5: The Straddle & Sequential Gate Protocol

### 1. The Straddle Split (Sub-Sectors)

When a reading arrival spans across one or more period boundaries (e.g., last reading Jan 20, next reading Mar 10):

**The Sector Engine MUST split the single physical sector into Virtual Sub-Sectors at every Period End Date (Jan 31, Feb 28).**

Each Sub-Sector uses Ratio Apportionment based on the days in that period.

**Formula:** `Sub-Sector Usage = Total Sector Usage × (Days in Period / Total Days in Sector)`

**Example:**
- Last Reading: Jan 20, 10,000 L
- Next Reading: Mar 10, 25,000 L
- Total Usage: 15,000 L over 50 days (Jan 20 to Mar 10 inclusive)

**Straddle Split:**
- Sub-Sector 1 (Jan 20-31): 12 days → 15,000 × (12/50) = 3,600 L
- Sub-Sector 2 (Feb 1-28): 28 days → 15,000 × (28/50) = 8,400 L  
- Sub-Sector 3 (Mar 1-10): 10 days → 15,000 × (10/50) = 3,000 L

### 2. The Sequential Gate (Slow Down)

**Rule:** Period N cannot calculate final usage until Period N-1 is Reconciled.

**Definition of Reconciled:** The `opening_reading` of Period N must equal the `calculated_closing` of Period N-1.

**Action:** If a reading is entered for Period 4, the system must recursively "Heal" Periods 1, 2, and 3 in sequence before finalizing Period 4.

```php
// RULE: Sequential Reconciliation Enforcement
public function calculatePeriod(Period $currentPeriod) {
    $prevPeriod = $currentPeriod->previous();
    
    if ($prevPeriod && is_null($prevPeriod->calculated_closing)) {
        // STOP: We cannot know the true opening of this period 
        // until the previous period's closing is healed.
        $this->triggerHealing($prevPeriod); 
    }
    
    // Only proceed once the 'Baton' is passed correctly
    $currentPeriod->opening_reading = $prevPeriod->calculated_closing;
}
```

### 3. The Precision Point: Sector Splitting Logic

This logic must be triggered the moment a reading is saved.

**Logic Flow:**
1. **Detection:** New Reading (R_new) at Date (D_new)
2. **Span:** Identify the previous Physical Reading (R_old) at Date (D_old)
3. **Boundary Check:** Are there any `period_end_dates` between D_old and D_new?
4. **Trigger:** If yes, for every boundary found:
   - Create a sub-sector from D_old to boundary_date
   - Update the `calculated_closing` of the period ending at that boundary
   - Set the `opening_reading` of the next period to that `calculated_closing`
5. **Resume:** Only then calculate the remaining usage for the current active period

### 4. The "Slow Down" Instruction (The Sequential Lock)

To prevent Period 4 from skipping the reconciliation of Period 3, implement this "Blocking" logic:

```php
// BLOCK-DAY MANDATE:
// Sub-Sector Splitting: You are now a "Straddle-Aware" engine.
// Sequential Dependency: You must "Slow Down."
// Never calculate Period N usage using a provisional opening.
// You MUST first trigger the healing of Period N-1 to generate a calculated_closing,
// which becomes the mandatory opening_reading for the current period.

// No Multi-Step Jumps: If the user adds a reading in May, and the last reading was in January,
// you must heal February, March, and April in a recursive loop before May can be calculated.
```

### 5. Implementation Checklist

- [x] **Straddle Detection:** Check if sector spans period boundaries
- [x] **Sub-Sector Generation:** Split at each boundary using Ratio Apportionment
- [x] **Sequential Gate:** Block Period N if Period N-1 has no `calculated_closing`
- [x] **Recursive Healing:** Heal all prior periods before calculating current
- [x] **Baton Pass:** Ensure `opening_reading` = previous `calculated_closing`

### 6. Section 0.5(C): The Weighted Projection Law

**Projections (filling the gap between the last reading and the period end) must use the Weighted Mean of the current period ($Usage_{total} / Blocks_{total}$) rather than the instantaneous rate of the last sector.**

**Why?** End-of-month spikes can skew the projection if we use the instantaneous rate of the last sector. For example:
- Last sector: Jan 25-28 (3 days) with 6000L usage = 2000 L/day
- Gap: Jan 29-31 (3 days)
- If we use 2000 L/day: Gap projection = 6000L
- But the period average might be 1000 L/day, so the correct projection should be 3000L

**Formula:**
```
Weighted Rate = Total Sector Usage / Total Sector Blocks
Gap Projection = Weighted Rate × Gap Blocks
```

### 7. Section 0.5(D): The Baton Pass Integrity

**The `calculated_closing` is a forensic anchor. It must be stored with a minimum of 2 decimal places to prevent rounding errors from compounding across sequential periods. The `opening_reading` of the next period must consume this value exactly.**

**Why?** Rounding errors compound across sequential periods. If Period 1's closing is rounded to 10000L but the actual value is 10000.47L, Period 2's opening will be off by 0.47L. Over many periods, this drift accumulates.

**Implementation:**
```php
// Store calculated_closing with 2 decimal places
$calculatedClosing = round($openingReading + $finalUsage, 2);

// Next period's opening MUST match exactly
$openingReading = $prevBill->calculated_closing; // No rounding!
```

**Sub-Sector Boundary Logic:**
- The `subEndReading` of Sub-Sector 1 is the EXACT `subStartReading` of Sub-Sector 2
- There must be zero "air" between blocks
- This prevents rounding errors from compounding across sequential periods

### 8. Section 0.5(E): The Integer Anchor Rule

**All `calculated_closing` and `consumption` values must be stored as Integers.**

**Why?** Decimals introduce rounding drift. When splitting a 15,000L sector across 3 periods, using `round()` on each sub-sector can result in 4,999 + 8,399 + 2,601 = 14,999L (1L lost). Over many periods, this accumulates.

**The Remainder Method:**

When splitting a straddle sector into sub-sectors:

1. **For sub-sectors 1 to N-1:** Use `floor(Total Usage × Ratio)` - always round DOWN
2. **For the final sub-sector (N):** Use `Total Sector Usage - sum(Previous Sub-Sectors)` - the remainder "snaps" to the physical reading

**Example:**
```
Total Sector Usage: 15,000L over 50 days
Sub-Sector 1 (12 days): floor(15000 × 12/50) = floor(3600) = 3600L
Sub-Sector 2 (28 days): floor(15000 × 28/50) = floor(8400) = 8400L
Sub-Sector 3 (10 days): 15000 - (3600 + 8400) = 3000L (REMAINDER)

Sum: 3600 + 8400 + 3000 = 15000L ✅ (No lost litres!)
```

**Implementation:**
```php
// Loop through sub-sectors 1 to N-1
foreach ($boundaries as $boundary) {
    $subUsage = (int) floor($totalSectorUsage * $ratio); // Round DOWN
    $runningTotal += $subUsage;
}

// The Final Piece "Snaps" to the physical reading
$finalSubUsage = $totalSectorUsage - $runningTotal; // Remainder
```

**This ensures that the sum of parts always equals the physical whole, with zero decimals and zero "lost" litres.**

### 9. Section 0.5(F): The Internal Architecture of CalculatorPHP.php

**The Four Functional Zones:**

Inside this single file, the logic is divided into these functional zones:

1. **The Sequential Gate (The Guard):** Checks if Period N-1 is reconciled. If not, it triggers the recursion.

2. **The Sector Engine (The Scientist):** Turns raw readings into physical sectors and handles Rollover Logic (e.g., meter goes from 9999 to 0001).

3. **The Straddle Splitter (The Surgeon):** The "Precision Point." It detects if a sector crosses a month-end and performs the Remainder Method split to keep things in whole Litres.

4. **The Distribution Engine (The Accountant):** Apportions the usage into the specific billing period and applies the Weighted Period Average for projections.

**Why it MUST stay in one place:**

If you split this logic across multiple files, you risk the "Drift" we are trying to avoid.

- **Atomicity:** By keeping it in one file, a single Database Transaction can wrap the entire "Healing Chain." If healing January fails, the system automatically rolls back any changes to February.

- **Traceability:** When you are debugging a 4-month silence gap, you can follow the logic from the gate to the split in one scroll.

**File Mandate:**

- `CalculatorPHP.php` is the **exclusive owner** of the Straddle Split and Sequential Gate logic.
- No other service should calculate "Usage." They should all request a calculation from this file.

**Method Privacy:**

- Keep the "Surgery" (splitting and healing) in **private methods**
- Expose only `computePeriod($billId)` as the **public entry point**

**The "Silence" Safety Net:**

Because this lives in the calculator, if you have 6 months of silence and then enter a reading in month 7, the `computePeriod` for month 7 will:

1. Detect the gap
2. Recursively call `healPeriod` for months 1 through 6
3. Each call will use the same `CalculatorPHP` logic to ensure the Baton Pass is identical across the whole chain

---

## Section 1: Core Billing Philosophy

### The 2-Step Process (MANDATORY)

All billing calculations MUST follow this exact sequence:

**Step 1: Establish Billing Days**
- Period boundaries are determined by the billing day (e.g., 20th of each month)
- Boundaries: billing_day → billing_day of next month
- Example: Bill day 20th → Period Dec 20 to Jan 19 (end exclusive)
- Days vary by month (28, 29, 30, 31) - account for leap years

**Step 2: Establish Daily Usage**
- Calculate daily usage from meter readings FIRST
- Daily Usage = (Reading₂ - Reading₁) / (Days between readings)
- This is the PRIMARY value - all else flows from this

**Step 3: Period Calculation**
- Total = Daily Usage × Billing Days
- This is derived, not calculated independently

### Period 1 - FIRST READING

For Period 1, the first reading in the readings list is used as the opening reading.

**Calculation for Period 1:**

1. **Derive daily usage** (usual way):
   - First reading: Jan 10 = 0L
   - Next reading: Jan 15 = 5000L
   - Daily usage = 5000L ÷ 5 days = **1000 L/day**

2. **Calculate period total**:
   - Days from first reading date to period_end_date
   - Jan 10 to Jan 31 = 22 days
   - Total = 1000 × 22 = **22000 L** ✅

**Key terms:**
- **Opening Reading**: Used for Period 1 (from first reading)
- **Closing Reading**: End of Period N = Opening + Usage
- **Baton Pass**: Period 2+ opening = Previous period closing

**Flow:**
- Period 1: Opening = First Reading → Closing = First Reading + Usage
- Period 2+: Opening = Previous Closing → Closing = Opening + Usage

---

## Section 2: Calculation Flow (The Golden Rules)

### MANDATORY SEQUENCE - DO NOT DEVIATE

```
1. PERIOD BOUNDARIES → 2. DAILY USAGE → 3. TOTAL USAGE
        ↓                        ↓                      ↓
   (from bill day)     (from readings)        (calculated)
```

### Golden Rule #1: NO Reverse Calculations

**NEVER calculate daily usage as:**
```php
$dailyUsage = $totalUsage / $daysInPeriod;  // ❌ FORBIDDEN
```

**ALWAYS use:**
```php
$dailyUsage = $rateFromSectors;  // ✅ CORRECT
```

The daily usage comes from the readings (Step 2), NOT from dividing total by days.

### Golden Rule #2: Single Inclusive Method

**ALL day calculations use the same inclusive method:**

- Period: Jan 1 to Jan 31 = 31 days (inclusive)
- Sector: Jan 1 to Jan 10 = 10 days (inclusive)

Both use `daysInclusive()` = diffInDays + 1

**Why?**
- Carbon's diffInDays excludes the end date
- We need both start AND end included in billing
- The reading on a day covers that entire day

### Golden Rule #3: Unified Day Calculation

All code uses the same helper: `daysInclusive($start, $end)`

| Context | Example | Result |
|---------|---------|--------|
| Period | Jan 1 to Jan 31 | 31 days |
| Sector | Jan 1 to Jan 10 | 10 days |
| Gap | Jan 11 to Jan 31 | 21 days |

---

## Section 3: Technical Implementation

### CalculatorPHP.php - Method Responsibilities

**Important**: We use a custom `daysInclusive()` helper that accounts for Carbon's diffInDays excluding the end date.

#### getBlockDays($start, $end) / daysInclusive($start, $end)
- **Purpose**: Calculate inclusive days (both start AND end are counted)
- **Logic**: diffInDays + 1 (to include both endpoints)
- **Example**: Jan 1 to Jan 10 = 10 days (1,2,3,4,5,6,7,8,9,10)
- **Why**: Replaces Carbon's diffInDays which excludes end date

```php
private function getBlockDays($start, $end): int
{
    $startDate = $this->asDate($start);
    $endDate = $this->asDate($end);
    return (int) $startDate->diffInDays($endDate) + 1;
}
```

#### generateSectors($readings, $meter)
- **Purpose**: Create usage sectors from reading pairs with Ratio Apportionment data
- **Logic**: Uses getBlockDays() for consistent inclusive calculation
- **Returns**: Array of sectors with start_reading, end_reading, total_usage, sector_blocks

```php
private function generateSectors($readings, $meter)
{
    // ...
    $sectors[] = [
        'start' => $d1,
        'end' => $d2,
        'start_reading' => $r1Value,    // Gatepost start
        'end_reading' => $r2Value,      // Gatepost end
        'total_usage' => $totalUsage,
        'sector_blocks' => $sectorBlocks
    ];
    // ...
}
```

#### distribute($periodStart, $periodEnd, $sectors, $seed)
- **Purpose**: Calculate total usage using Ratio Apportionment
- **Logic**: 
  1. Find overlap between sector and period
  2. Apply ratio: `usage = sector_usage × (overlap_blocks / sector_blocks)`
  3. Project remaining days using last known rate
- **Returns**: ['usage' => total, 'rate' => dailyRate]

```php
private function distribute($periodStart, $periodEnd, $sectors, $dailyUsageSeed = 0)
{
    // RATIO APPORTIONMENT - prevents rounding drift
    if ($overlapStart->lte($overlapEnd)) {
        $overlapBlocks = $this->getBlockDays($overlapStart, $overlapEnd);
        $ratio = $overlapBlocks / $sectorBlocks;
        $overlapUsage = $sectorUsage * $ratio;  // ✅ Ratio method
        $totalUsage += $overlapUsage;
    }
    
    return [
        'usage' => round($totalUsage, 0),
        'rate' => $lastKnownRate
    ];
}
```

#### getOpeningReading($bill, $readings, $meter) - THE HEALED BATON
- **Purpose**: Determine opening reading with dynamic healing
- **Logic**:
  1. IF previous bill exists, try to find "Healed Opening" from overlapping sector
  2. Use sector's `end_reading` where sector overlaps previous period end
  3. Fallback to previous bill's `calculated_closing`
  4. IF no previous bill, use first reading (Period 1)
  5. Final fallback: bill's declared opening_reading

```php
private function getOpeningReading($bill, $readings, $meter)
{
    // Try to heal from overlapping sector
    foreach ($sectors as $sector) {
        if ($sectorStart->lte($prevEndDate) && $sectorEnd->gte($prevEndDate)) {
            $healedOpening = floor((float) $sector['end_reading']);
            return $healedOpening;
        }
    }
    // Fallback chain...
}
```

---

## Section 4: Anti-Drift Rules

### NEVER DO THESE

1. ❌ **Calculate daily usage as usage ÷ days**
   - This creates a reverse calculation
   - The 969 L/day bug was caused by this

2. ❌ **Add +1 to billing days (getForensicDays)**
   - Period end is exclusive
   - Feb 1 means billing up to Jan 31 = 31 days

3. ❌ **Use different day counts for rate vs usage**
   - If rate uses inclusive days (+1), usage must also
   - If rate uses exclusive days, usage must also

### ALWAYS DO THESE

1. ✅ **Calculate rate from readings first**
2. ✅ **Use that rate throughout**
3. ✅ **Test with known values**:
   - 10 days, 10000L → 1000 L/day
   - 31 days × 1000 L/day → 31000 L

---

## Section 5: Validation Checkpoints

### Test Scenario 1: Simple Period
- Readings: Jan 1 = 0, Jan 10 = 10000
- Period: Jan 1 to Feb 1
- Expected:
  - Billing Days: 31
  - Daily Usage: 1000 L/day
  - Total: 31000 L

### Test Scenario 2: Partial Period
- Readings: Jan 15 = 5000, Jan 25 = 15000
- Period: Jan 1 to Feb 1
- Expected:
  - Jan 1-14: Use seed/momentum
  - Jan 15-25: 10000L ÷ 10 days = 1000 L/day
  - Jan 26-31: 1000 L/day × 6 = 6000 L

### Test Scenario 3: Multiple Months with Healing
- Billing day: 20th
- January finalized with Provisional Closing: 38,750 L
- February receives reading on Feb 10: 55,000 L
- Expected:
  - January Calculated Closing updates via Ratio Apportionment
  - February Opening Reading syncs to January's Calculated Closing
  - January Provisional Closing remains 38,750 L (immutable)

---

## Section 6: Code Examples

### Correct Implementation

```php
// ✅ CORRECT - Daily usage from readings
$sectors = $this->generateSectors($readings, $meter);
$calcResult = $this->distribute($periodStart, $periodEnd, $sectors, $seed);
$finalUsage = $calcResult['usage'];        // 31000
$dailyUsage = $calcResult['rate'];          // 1000 (from readings!)
$daysInPeriod = $this->getBlockDays($periodStart, $periodEnd);  // 31
```

### Incorrect Implementation (THE BUG)

```php
// ❌ WRONG - Reverse calculation
$sectors = $this->generateSectors($readings, $meter);
$calcResult = $this->distribute($periodStart, $periodEnd, $sectors, $seed);
$finalUsage = $calcResult['usage'];        // 31000
$daysInPeriod = $this->getBlockDays($periodStart, $periodEnd);  // 31 (or 32 with bug)
$dailyUsage = $finalUsage / $daysInPeriod; // 969 (WRONG!)
```

---

## Section 7: Key Differences from Legacy Systems

| Concept | Legacy (WRONG) | Current (CORRECT) |
|---------|----------------|-------------------|
| Billing Days | +1 added | No +1 (exclusive end) |
| Rate Calculation | Reverse engineered | From readings |
| Daily Usage Display | usage ÷ days | From sectors |
| Period End | Sometimes inclusive | Always exclusive |
| Opening Reading | Static | Dynamic (Healed) |
| Closing Reading | Single value | Provisional + Calculated |

---

## Section 8: The Gatepost Protocol (Reading Immutability)

The system distinguishes between the Closing Reading of a finalized period and the Opening Reading of the subsequent period.

### Key Concepts

**Provisional Closing (Immutable):**
- A snapshot of the calculated state at the moment a billing period is finalized
- Once the invoice is generated, this value is locked
- Cannot be changed retroactively

**Calculated Closing (Dynamic):**
- The "Healed Truth" after later readings arrive
- Updates via Ratio Apportionment when new readings span period boundaries
- Used for the next period's Opening Reading

**Opening Reading (Mutable/Healing):**
- The baseline for the active period
- Initially seeded by the previous period's Provisional Closing
- Updates to match previous period's Calculated Closing when healing occurs

### The Healing Rule

If a physical meter reading arrives that contradicts the seeded Opening Reading, the Opening Reading "heals" to the physical truth. This creates a "Reconciliation Gap" between periods that is absorbed by the current period's usage calculation, ensuring historical integrity.

### Block-Day Alignment

Readings are "Gateposts" existing at the boundary of Day Blocks. A reading on Feb 1st is the baseline for Feb 1st's consumption.

---

## Section 9: DATABASE FIELDS FOR GATEPOST PROTOCOL

### Bills Table Fields

| Field | Type | Purpose |
|-------|------|---------|
| `opening_reading` | decimal(15,2) | Dynamic opening (heals with new readings) |
| `provisional_closing` | decimal(15,2) | Immutable snapshot at finalization |
| `calculated_closing` | decimal(15,2) | Dynamic "Healed Truth" |
| `is_provisional` | boolean | True until finalized |
| `status` | string | PROVISIONAL, CALCULATED, FINALIZED |

### Meters Table Fields

| Field | Type | Purpose |
|-------|------|---------|
| `start_reading` | decimal(15,2) | Genesis reading for Period 1 |
| `start_reading_date` | date | Date of genesis reading |
| `digit_count` | int | For rollover calculation (default 4) |

---

## Section 10: UI & State Management - Genesis and Gatepost Rules

### The Genesis (Start Reading)

Period 1 (and only Period 1) must contain a "Start Reading" field. This is the absolute initialization point of the meter.

### The One-Way Valve UI

Every period displays a **Provisional Closing** and a **Calculated Closing**.

**Provisional Closing:**
- The estimate or reading used when the period was first finalized.
- Immutable.

**Calculated Closing:**
- The "Healed" truth after later readings arrive.
- The UI must explicitly show the Difference to represent reconciliation.

### The Opening Handshake

The Opening Reading for Period $N+1$ is a dynamic field that:
- **INITIAL:** Defaults to the previous period's Provisional Closing.
- **RECONCILED:** Updates to the previous period's Calculated Closing (Physical Truth) once reconciliation occurs.

---

## Section 11: UI REFACTOR - IMPLEMENT GENESIS READING & GATEPOST RECONCILIATION

### 1. PERIOD 1: THE GENESIS ROW

- **Conditional Rendering:** IF `period_index === 0`, render a new row immediately below the "Add Period" action line.
- **Fields:**
  - **Label:** "Start Reading (Initialization)"
  - **Date Input:** Default to the start date of Period 1.
  - **Reading Input:** Integer value representing the meter baseline.
- **Logic:** This reading serves as the `R1` for the first sector of Period 1.

### 2. PERIOD FOOTER: CLOSING & RECONCILIATION

Below the readings table for EACH period, add a "Closing Summary" section containing:

#### A. Provisional Closing Reading
- **Label:** "Provisional Closing"
- **Behavior:** Captured at the moment of period finalization/billing. 
- **Constraint:** Set as READ-ONLY (Immutable) once the period is marked 'Finalized'.

#### B. Calculated Closing Reading (The Healing Field)
- **Label:** "Calculated Closing"
- **Behavior:** Defaults to empty. 
- **Logic:** When a physical reading is received *after* finalization that overlaps this period, calculate the "Healed Truth" using the Block-Day Ratio Apportionment.
- **Display:** Show the value and the variance: `Difference: [+/-] X litres`.

### 3. PERIOD N+1: DYNAMIC OPENING

- **Label:** "Opening Reading"
- **Source:** 
  - INITIAL: `previous_period.provisional_closing`.
  - RECONCILED: `previous_period.calculated_closing` (Physical Truth).
- **Visual Cue:** If the Opening Reading differs from the previous Provisional Closing, highlight the field in a 'Reconciliation Blue' to show the gap has been healed.

### 4. MATHEMATICAL SYNC

- Update the UI state to ensure that the "Daily Usage" for Period 1 is calculated as:
  `Usage = (First_Actual_Reading - Start_Reading)`.
- Ensure the math follows the Block-Day rule: `Days = (Reading_Date - Start_Reading_Date) + 1`.

---

## Summary of the Logic for Cursor

1. **Period 1 Only:** Show the Start Reading row at the top.
2. **Every Period:** Show Provisional (Historical) and Calculated (The Truth) at the bottom.
3. **Handshake:** The Opening Reading of the next period must "listen" for the Calculated Closing of the previous one.
4. **Ratio Apportionment:** Always use `usage = total × (overlap_blocks / sector_blocks)` to prevent drift.
5. **Healing:** When new readings arrive, update Calculated Closing and sync next period's Opening.


---

## Final Verification Checklist for Cursor

When implementing or modifying the billing system, verify these three critical alignment points:

### 1. Integer Anchor Rule (Section 0.5(E))
- [ ] Use `floor()` for sub-sectors 1 to N-1
- [ ] Final sub-sector uses `$totalSectorUsage - $runningTotal` (Remainder Method)
- [ ] All values cast to `(int)` after calculation
- [ ] **Verification:** Sum of sub-sectors equals physical meter reading exactly

### 2. UI Reconciliation Blue (Section 11(3))
- [ ] Opening Reading highlights when `calculated_closing` differs from `provisional_closing`
- [ ] Visual cue shows the gap has been healed
- [ ] **Verification:** User can see when healing has occurred

### 3. Day Calculation Consistency (Section 2)
- [ ] `daysInclusive()` (Start to End + 1) used for Sectors
- [ ] Period Boundary (e.g., 20th to 20th) remains 1 month of billing days
- [ ] **Verification:** Sector days and period days use same inclusive method

### 4. Mandatory Sequential Handshake (Baton Pass)

The `getHealedOpening()` method MUST implement the Baton Pass like this:

```php
// MANDATORY SEQUENTIAL HANDSHAKE
private function getHealedOpening($currentBill) 
{
    $prevBill = $currentBill->previous();
    
    // If the previous month isn't healed, trigger the recursive gate
    if ($prevBill && is_null($prevBill->calculated_closing)) {
        $this->computePeriod($prevBill->id); 
    }

    // Pass the baton: The Healed Truth is the only valid opening
    return $prevBill->calculated_closing ?? $prevBill->provisional_closing;
}
```

**Key Points:**
- [ ] If previous bill has no `calculated_closing`, recursively call `computePeriod()`
- [ ] This triggers the healing chain automatically
- [ ] Return `calculated_closing` (Healed Truth) with fallback to `provisional_closing`
- [ ] **Verification:** Opening Reading always matches previous period's closing

---

## Calculator Implementation Checklist (for Calculator.php)

Use this checklist when implementing or refactoring `Calculator.php`. Every rule below must be reflected in code. If code drifts from PD, fix the code to match this document.

### Section 0 – Block-Day & State Rules
- [ ] **Provisional closing immutability:** Never update `provisional_closing` when `status` is FINALIZED or INVOICED.
- [ ] **Opening reading source:** `opening = prev.calculated_closing ?? prev.provisional_closing ?? firstReading ?? meter.start_reading`.
- [ ] **Calculated closing:** Populated only via healing (sector overlap with period end); never from reverse calculation.
- [ ] **Ratio apportionment:** Usage = Total_Sector_Units × (Overlap_Blocks / Total_Sector_Blocks).

### Section 0.5 – Straddle & Sequential Gate
- [ ] **Straddle split:** Sector spanning period boundaries is split into sub-sectors at each period end date; each sub-sector uses ratio apportionment.
- [ ] **Sequential gate:** Period N cannot calculate until Period N-1 has `calculated_closing`; if not, recursively heal N-1 first.
- [ ] **Recursive healing:** Heal all prior periods in sequence before calculating current period (no multi-step jumps).
- [ ] **Baton pass:** Current period `opening_reading` = previous period `calculated_closing` (or provisional fallback).
- [ ] **Weighted projection:** Gap projection uses weighted rate = Total Sector Usage / Total Sector Blocks, not instantaneous rate of last sector.
- [ ] **Baton pass integrity:** Store `calculated_closing` with sufficient precision; next period opening consumes it exactly (no rounding at handoff).
- [ ] **Integer anchor (Remainder Method):** Sub-sectors 1 to N-1 use `floor(TotalUsage × ratio)`; final sub-sector = Total - sum(previous). All consumption/calculated_closing as integers. Sum of sub-sectors must equal physical total (zero lost litres).
- [ ] **Four zones in one file:** Sequential Gate (guard), Sector Engine (sectors + rollover), Straddle Splitter (remainder split), Distribution Engine (ratio apportion + weighted projection). Single public entry: `computePeriod($billId)`; surgery in private methods.

### Section 1 – Billing Philosophy
- [ ] **Order:** (1) Period boundaries from bill day, (2) Daily usage from readings, (3) Total usage derived (never reverse: total/days).
- [ ] **Period boundaries:** Leap-year and month-length aware; billing_day → billing_day next month (end exclusive).
- [ ] **Period 1:** Opening = first reading or meter start_reading; closing = opening + usage.

### Section 2 – Golden Rules
- [ ] **No reverse calculation:** Never `dailyUsage = totalUsage / daysInPeriod`.
- [ ] **Single inclusive method:** All day counts use `daysInclusive(start, end)` = diffInDays + 1 (sectors and periods).
- [ ] **One helper:** All code uses the same `daysInclusive` / `getBlockDays` for consistency.

### Section 3 – Method Responsibilities
- [ ] **getBlockDays / daysInclusive:** Inclusive days; diffInDays + 1.
- [ ] **generateSectors:** From reading pairs; uses getBlockDays; returns start_reading, end_reading, total_usage, sector_blocks; handles rollover.
- [ ] **distribute:** Overlap sector vs period; ratio = overlap_blocks / sector_blocks; usage = sector_usage × ratio; gap uses weighted rate; returns ['usage' => int, 'rate' => dailyRate].
- [ ] **getOpeningReading / getHealedOpening:** Healed from overlapping sector at prev period end; fallback chain: calculated_closing → provisional_closing → first reading → start_reading. If prev has no calculated_closing, recursively call computePeriod(prev).

### Section 4 – Anti-Drift
- [ ] Never calculate daily usage as usage ÷ days.
- [ ] Do not add +1 to billing days (period end is exclusive).
- [ ] Same day-count method for rate and usage.

### Section 9 – Database Fields (reference)
- [ ] Bills: opening_reading, provisional_closing, calculated_closing, status. Meters: start_reading, start_reading_date, digit_count.

### Public API (Calculator.php)
- [ ] `computePeriod(int $billId)`: load bill, meter, readings; run gate → sectors → straddle → distribution; persist; return result.
- [ ] `ensureBillAndCompute($meterId, $periodStart, $periodEnd, $accountId = null)`: find or create bill; then computePeriod($billId).
- [ ] Period boundary methods: findPeriodStartForDate, calculatePeriodEnd, findPeriodForDate, calculatePeriods (for callers that need period dates only).

---

**Last Updated**: 2026-02-20
**Purpose**: Prevent calculation drift and ensure forensic accuracy
**Enforcement**: All billing code must follow Section 0-3 exactly. Calculator.php must satisfy the checklist above.
