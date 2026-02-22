#!/bin/bash

# ==================================================
# ZERO CALCULATION RULE - CI GUARD
# ==================================================
# 
# This script enforces the architectural rule:
# ZERO billing calculations are permitted outside the Billing Calculator.
#
# Calculator boundary (ONLY allowed locations):
# - app/Services/BillingEngine.php
# - app/Services/BillingPeriodCalculator.php
# - app/Services/DateToDatePeriodCalculator.php
#
# EVERYWHERE ELSE is FORBIDDEN.
#
# ==================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Calculator files (excluded from checks)
CALCULATOR_FILES=(
    "app/Services/BillingEngine.php"
    "app/Services/BillingPeriodCalculator.php"
    "app/Services/DateToDatePeriodCalculator.php"
)

# Build exclude pattern for grep
EXCLUDE_PATTERN=""
for file in "${CALCULATOR_FILES[@]}"; do
    if [ -z "$EXCLUDE_PATTERN" ]; then
        EXCLUDE_PATTERN="--exclude=$file"
    else
        EXCLUDE_PATTERN="$EXCLUDE_PATTERN --exclude=$file"
    fi
done

# Search directory
SEARCH_DIR="app"

# Track violations
VIOLATIONS=0
VIOLATION_LOG=""

echo "=========================================="
echo "ZERO CALCULATION RULE - CI GUARD"
echo "=========================================="
echo ""
echo "Scanning: $SEARCH_DIR"
echo "Excluding calculator files:"
for file in "${CALCULATOR_FILES[@]}"; do
    echo "  - $file"
done
echo ""

# ==================================================
# PATTERN 1: Arithmetic operators on billing values
# ==================================================
echo "Checking for arithmetic operators on billing values..."

# Pattern: billing_variable = ... * | / | + | - ... (excluding comments and strings)
PATTERN1_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "(usage|consumption|projected|daily_usage|total_usage)\s*=\s*[^=]*[*+/%-]" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN1_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Arithmetic operators on billing values${NC}"
    echo "$PATTERN1_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 1: Arithmetic operators on billing values"
fi

# Pattern: ... * | / | + | - billing_variable (excluding comments and strings)
PATTERN1B_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "[*+/%-]\s*(usage|consumption|projected|daily_usage|total_usage)" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN1B_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Arithmetic operators with billing values${NC}"
    echo "$PATTERN1B_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 1B: Arithmetic operators with billing values"
fi

# ==================================================
# PATTERN 2: Time arithmetic functions
# ==================================================
echo "Checking for time arithmetic functions..."

# Pattern: diffInDays|addDays|subDays|days_between (excluding comments)
PATTERN2_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "(diffInDays|addDays|subDays|days_between)\s*\(" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN2_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Time arithmetic functions detected${NC}"
    echo "$PATTERN2_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 2: Time arithmetic functions"
fi

# ==================================================
# PATTERN 3: Daily usage calculations
# ==================================================
echo "Checking for daily usage calculations..."

# Pattern: daily_usage = total_usage / days (excluding comments)
PATTERN3_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "daily_usage\s*=\s*[^=]*(usage|consumption)[^=]*[/%]" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN3_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Daily usage calculations detected${NC}"
    echo "$PATTERN3_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 3: Daily usage calculations"
fi

# ==================================================
# PATTERN 4: Projection calculations
# ==================================================
echo "Checking for projection calculations..."

# Pattern: projected = daily * days (excluding comments)
PATTERN4_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "projected.*=\s*[^=]*(daily|usage)[^=]*[*]" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN4_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Projection calculations detected${NC}"
    echo "$PATTERN4_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 4: Projection calculations"
fi

# ==================================================
# PATTERN 5: Average calculations
# ==================================================
echo "Checking for average calculations..."

# Pattern: average = usage / days (excluding comments)
PATTERN5_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "(average|avg)\s*=\s*[^=]*(usage|consumption)[^=]*[/%]" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN5_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Average calculations detected${NC}"
    echo "$PATTERN5_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 5: Average calculations"
fi

# ==================================================
# PATTERN 6: Usage subtraction (reading difference)
# ==================================================
echo "Checking for usage calculations from readings..."

# Pattern: usage = reading - reading (excluding comments)
PATTERN6_RESULTS=$(grep -rn "$EXCLUDE_PATTERN" \
    --include="*.php" \
    -E "(usage|consumption)\s*=\s*[^=]*reading[^=]*[-]" \
    "$SEARCH_DIR" 2>/dev/null | \
    grep -vE "^\s*//|^\s*/\*|\*/\s*$|^\s*\*|REMOVED|FORBIDDEN|deprecated|TODO|FIXME|@deprecated|DEPRECATED" || true)

if [ ! -z "$PATTERN6_RESULTS" ]; then
    echo -e "${RED}VIOLATION: Usage calculations from readings detected${NC}"
    echo "$PATTERN6_RESULTS"
    echo ""
    VIOLATIONS=$((VIOLATIONS + 1))
    VIOLATION_LOG="${VIOLATION_LOG}\nPattern 6: Usage calculations from readings"
fi

# ==================================================
# SUMMARY
# ==================================================
echo "=========================================="
if [ $VIOLATIONS -eq 0 ]; then
    echo -e "${GREEN}SUCCESS: No billing calculations detected outside calculator${NC}"
    echo ""
    echo "The codebase complies with the ZERO CALCULATION RULE."
    exit 0
else
    echo -e "${RED}FAILURE: $VIOLATIONS violation(s) detected${NC}"
    echo ""
    echo "The following patterns were detected outside the calculator:"
    echo -e "$VIOLATION_LOG"
    echo ""
    echo -e "${RED}ARCHITECTURAL VIOLATION${NC}"
    echo ""
    echo "ZERO billing calculations are permitted outside:"
    for file in "${CALCULATOR_FILES[@]}"; do
        echo "  - $file"
    done
    echo ""
    echo "All billing calculations MUST be performed by the Billing Calculator."
    echo "Do not perform any arithmetic operations on billing values outside these files."
    echo ""
    exit 1
fi

