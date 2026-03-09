#Requires -Version 5.1
# Installs the calculator architecture check as a git pre-commit hook.
# Run from repo root: .\scripts\install-pre-commit.ps1
$hookDir = Join-Path (Get-Location) ".git\hooks"
$source = Join-Path (Get-Location) "scripts\git-hooks\pre-commit"
$dest = Join-Path $hookDir "pre-commit"
if (-not (Test-Path ".git")) {
    Write-Error "Not a git repo root. Run from MyCities-Core root."
    exit 1
}
if (-not (Test-Path $source)) {
    Write-Error "Source hook not found: $source"
    exit 1
}
if (-not (Test-Path $hookDir)) {
    New-Item -ItemType Directory -Path $hookDir -Force | Out-Null
}
Copy-Item -Path $source -Destination $dest -Force
Write-Host "Pre-commit hook installed at .git/hooks/pre-commit"
Write-Host "It runs the calculator architecture check before each commit."
