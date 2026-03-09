# Development Guidelines - Preventing Duplicates

## Problem Statement

This codebase has been plagued with duplicate files, methods, and code patterns. This document outlines the root causes and solutions.

## Root Causes

### 1. **Copy-Paste During Refactoring**
- **Problem**: When refactoring, developers copy code instead of moving it
- **Example**: Creating a new method by copying an existing one, then forgetting to remove the old one
- **Solution**: Always use "Move" refactoring in IDE, never copy-paste

### 2. **Search-Replace Mistakes**
- **Problem**: Find-replace operations can accidentally create duplicates
- **Example**: Replacing a method signature but leaving the old one
- **Solution**: Use IDE refactoring tools, not manual find-replace

### 3. **Docker Volume Sync Issues**
- **Problem**: Files edited locally may not sync to Docker immediately
- **Example**: Editing a file, Docker still has old version, making changes in Docker creates duplicates
- **Solution**: Always verify file sync before making changes

### 4. **No Pre-Commit Validation**
- **Problem**: Duplicates slip through because there's no automated check
- **Solution**: Use pre-commit hooks (see below)

### 5. **Manual Editing Without IDE Support**
- **Problem**: Editing files manually without IDE refactoring support
- **Solution**: Always use IDE refactoring features

## Solutions Implemented

### 1. Pre-Commit Hook
**Location**: `.git/hooks/pre-commit`
**Purpose**: Automatically checks for duplicates before each commit
**Usage**: Runs automatically on `git commit`

```bash
# Make hook executable (one-time setup)
chmod +x .git/hooks/pre-commit
```

### 2. Duplicate Prevention Script
**Location**: `scripts/PreventDuplicates.ps1`
**Purpose**: Scans for duplicate methods and code blocks
**Usage**:
```powershell
# Manual check
.\scripts\PreventDuplicates.ps1

# Pre-commit mode (fails on duplicates)
.\scripts\PreventDuplicates.ps1 -PreCommit
```

### 3. Workspace Health Script
**Location**: `scripts/WorkspaceHealth.ps1`
**Purpose**: Comprehensive health check including duplicates
**Usage**: Run before major changes

### 4. Development Workflow

#### Before Making Changes:
1. ✅ Run `.\scripts\PreventDuplicates.ps1` to check current state
2. ✅ Run `.\scripts\WorkspaceHealth.ps1` for full health check
3. ✅ Check Docker file sync: `docker exec mycities-laravel ls -la /var/www/html/app/Services/`

#### During Development:
1. ✅ **NEVER** copy-paste methods - use IDE "Extract Method" refactoring
2. ✅ **NEVER** use find-replace for method signatures - use IDE "Rename" refactoring
3. ✅ **ALWAYS** verify file sync after editing (check file size/line count in Docker)
4. ✅ **ALWAYS** run syntax check: `docker exec mycities-laravel php -l /path/to/file.php`

#### Before Committing:
1. ✅ Pre-commit hook runs automatically
2. ✅ If hook fails, fix duplicates before committing
3. ✅ Run manual check: `.\scripts\PreventDuplicates.ps1 -PreCommit`

### 5. Calculator architecture check
**Location**: `scripts/check-calculator-architecture.ps1`  
**Purpose**: Enforces PrimaryRules.mdc / ProjectDescription.md — period and sector logic only in Calculator.php / Calendar.php, not in Calculator.vue.  
**When it runs**: (1) Before every Docker build (`Build_Core.cmd` / `BuildDocker_Core.ps1`). (2) Before every commit if you install the pre-commit hook — run `.\scripts\install-pre-commit.ps1` from repo root once; then the hook at `.git/hooks/pre-commit` runs the same check and blocks commit on failure.  
**Rule**: Calculator.vue must not contain period/sector-building logic (e.g. `buildD2dPeriodsFromAnchorReadings`). Vue only calls backend APIs and renders. See ProjectDescription.md and PrimaryRules.mdc.

## Best Practices

### ✅ DO:
- Use IDE refactoring tools (Rename, Extract Method, Move)
- Run duplicate checks before committing
- Verify Docker file sync after editing
- Use version control (git) to track changes
- Review diffs before committing

### ❌ DON'T:
- Copy-paste methods or code blocks
- Use find-replace for method signatures
- Edit files in Docker directly (edit locally, let sync handle it)
- Commit without running checks
- Ignore pre-commit hook failures

## IDE Setup

### VS Code / Cursor
Install extensions:
- **PHP Intelephense** - Detects duplicate method names
- **SonarLint** - Detects code duplication
- **Error Lens** - Shows errors inline

### PHPStorm
Enable:
- Settings → Editor → Inspections → PHP → Code Smell Detection → Duplicate code
- Settings → Editor → Inspections → PHP → Code Smell Detection → Duplicate method

## CI/CD Integration

Add to your CI pipeline:
```yaml
# .github/workflows/ci.yml
- name: Check for duplicates
  run: |
    powershell -ExecutionPolicy Bypass -File scripts/PreventDuplicates.ps1 -PreCommit
```

## Monitoring

### Regular Checks
- **Daily**: Run `WorkspaceHealth.ps1` after major changes
- **Before Release**: Full duplicate scan
- **After Merge**: Verify no duplicates introduced

### Metrics to Track
- Number of duplicate methods found
- Files with most duplicates
- Common duplicate patterns

## Emergency Fixes

If duplicates are found in production:

1. **Identify**: Run `PreventDuplicates.ps1` to find all duplicates
2. **Backup**: Create restore point before fixing
3. **Fix**: Remove duplicates, keeping the most recent/correct version
4. **Test**: Run full test suite
5. **Verify**: Run duplicate check again
6. **Commit**: Commit fix with message "Fix: Remove duplicate [method/file]"

## Questions?

If you find duplicates:
1. Check this document first
2. Run `PreventDuplicates.ps1` to identify all instances
3. Determine which version is correct
4. Remove duplicates
5. Update this document if you find new patterns

