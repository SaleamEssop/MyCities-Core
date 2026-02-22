# CI Guards

This directory contains automated guards that enforce architectural rules.

## ZERO CALCULATION RULE GUARD

**File:** `check_no_billing_math_outside_calculator.sh`

**Purpose:** Enforces the architectural rule that ZERO billing calculations are permitted outside the Billing Calculator.

### Allowed Locations (Calculator Boundary)

Billing calculations are allowed ONLY in:
- `app/Services/BillingEngine.php`
- `app/Services/BillingPeriodCalculator.php`
- `app/Services/DateToDatePeriodCalculator.php`

### Forbidden Patterns

The script checks for:
1. Arithmetic operators on billing values (`+`, `-`, `*`, `/`)
2. Time arithmetic functions (`diffInDays`, `addDays`, `subDays`, `days_between`)
3. Daily usage calculations (`daily_usage = total_usage / days`)
4. Projection calculations (`projected = daily * days`)
5. Average calculations (`average = usage / days`)
6. Usage calculations from readings (`usage = reading - reading`)

### Usage

**Local Testing:**
```bash
bash ci/check_no_billing_math_outside_calculator.sh
```

**CI Integration (GitHub Actions):**
```yaml
- name: Check Zero Calculation Rule
  run: bash ci/check_no_billing_math_outside_calculator.sh
```

**CI Integration (GitLab CI):**
```yaml
zero_calculation_check:
  script:
    - bash ci/check_no_billing_math_outside_calculator.sh
```

**CI Integration (Jenkins):**
```groovy
stage('Zero Calculation Rule') {
    steps {
        sh 'bash ci/check_no_billing_math_outside_calculator.sh'
    }
}
```

### Exit Codes

- `0` - SUCCESS: No violations detected
- `1` - FAILURE: Violations detected (blocks CI)

### Notes

- The script excludes comments and deprecated code markers
- False positives may occur in comments/documentation - these should be ignored or the script refined
- This is an architectural guard, not a style check - violations must be fixed










