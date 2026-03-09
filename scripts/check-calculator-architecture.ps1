#Requires -Version 5.1
# Enforces PrimaryRules.mdc / ProjectDescription.md: billing and period logic only in PHP.
# Run before Docker build (BuildDocker_Core.ps1). Exit 0 = pass, 1 = fail.
#
# Core calculation files (do not extend):
#   1. app/Services/Billing/Calculator.php   - source of truth for all billing math (PD 1-12, 5.1, 5.2)
#   2. app/Services/Billing/Calendar.php    - all date math (PD 5.0)
#   3. app/Http/Controllers/Admin/CalculatorController.php - HTTP bridge only; no billing logic
#   4. resources/js/Pages/Admin/Calculator.vue - sole UI; no period/sector building from readings
#
# What we check:
#   - Calculator.vue must NOT contain: buildD2dPeriodsFromAnchorReadings (D2D period building belongs in Calculator.php)
#   - Calculator.php must contain: buildD2dPeriodsFromAnchorReadings (source of truth must have it)
#   - Calculator.vue must NOT contain: function that builds periods from anchor + readings (same as first; this is the key forbidden name)

param(
    [string]$RepoRoot = ""
)
if (-not $RepoRoot -or -not (Test-Path $RepoRoot)) {
    $RepoRoot = (Get-Item $PSScriptRoot).Parent.FullName
}
$vuePath = Join-Path $RepoRoot "resources\js\Pages\Admin\Calculator.vue"
$calcPhpPath = Join-Path $RepoRoot "app\Services\Billing\Calculator.php"
$errors = @()

# 1) Vue must NOT define or call buildD2dPeriodsFromAnchorReadings (period building in PHP only)
if (Test-Path $vuePath) {
    $vueContent = Get-Content $vuePath -Raw
    if ($vueContent -match "buildD2dPeriodsFromAnchorReadings") {
        $errors += "Calculator.vue must NOT contain 'buildD2dPeriodsFromAnchorReadings'. D2D period building belongs in Calculator.php (PrimaryRules.mdc)."
    }
} else {
    $errors += "Calculator.vue not found at $vuePath"
}

# 2) Calculator.php must contain the D2D period builder (source of truth)
if (Test-Path $calcPhpPath) {
    $phpContent = Get-Content $calcPhpPath -Raw
    if ($phpContent -notmatch "function buildD2dPeriodsFromAnchorReadings") {
        $errors += "Calculator.php must define 'buildD2dPeriodsFromAnchorReadings'. It is the source of truth for D2D periods (ProjectDescription.md 5.2)."
    }
} else {
    $errors += "Calculator.php not found at $calcPhpPath"
}

if ($errors.Count -gt 0) {
    Write-Host ""
    Write-Host "   Calculator architecture check FAILED (PrimaryRules.mdc / ProjectDescription.md):" -ForegroundColor Red
    foreach ($e in $errors) {
        Write-Host "   - $e" -ForegroundColor Red
    }
    Write-Host "   Fix the above before building." -ForegroundColor Yellow
    Write-Host ""
    exit 1
}
exit 0
