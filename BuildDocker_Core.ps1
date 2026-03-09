#Requires -Version 5.1
# MyCities-Core Docker Build Script (adapted from Cline)
# Single app: Laravel + MySQL + Nginx (no separate frontend).

param(
    [switch]$RebuildAll,  # Clear caches + build all + start (no menu)
    [switch]$NoCache      # Build with --no-cache (full rebuild)
)

$Host.UI.RawUI.WindowTitle = "MyCities-Core Docker Build"

# =============================================================================
#   CONFIGURATION
# =============================================================================

$Script:DockerPath = $PSScriptRoot
$Script:InfraPath = "$PSScriptRoot\infrastructure"
$Script:LaravelPath = $PSScriptRoot

$Script:ContainerLaravel = "mycities-core-laravel"
$Script:ContainerNginx = "mycities-core-nginx"
$Script:ContainerMysql = "mycities-core-mysql"

# =============================================================================
#   DISPLAY FUNCTIONS
# =============================================================================

function Write-Header {
    param([int]$Version = 0)
    Clear-Host
    Write-Host ""
    Write-Host "  ===========================================================================" -ForegroundColor Cyan
    Write-Host "   M Y C I T I E S - C O R E   D O C K E R   B U I L D" -ForegroundColor Cyan
    if ($Version -gt 0) {
        Write-Host "   Build v$Version" -ForegroundColor Yellow
    }
    Write-Host "  ===========================================================================" -ForegroundColor Cyan
    Write-Host ""
}

function Write-Success { param([string]$Msg) Write-Host "   [OK] $Msg" -ForegroundColor Green }
function Write-Err { param([string]$Msg) Write-Host "   [ERROR] $Msg" -ForegroundColor Red }
function Write-Info { param([string]$Msg) Write-Host "   [INFO] $Msg" -ForegroundColor Cyan }
function Write-Warn { param([string]$Msg) Write-Host "   [WARN] $Msg" -ForegroundColor Yellow }
function Write-Step { param([int]$N, [int]$T, [string]$Msg) Write-Host "   [$N/$T] $Msg" -ForegroundColor Yellow }

# =============================================================================
#   ENV FILE
# =============================================================================

function Ensure-EnvFile {
    $envExample = Join-Path $Script:InfraPath ".env.example"
    $envFile = Join-Path $Script:InfraPath ".env"
    if (-not (Test-Path $envFile)) {
        if (Test-Path $envExample) {
            Copy-Item $envExample $envFile
            Write-Success "Created infrastructure/.env from .env.example"
        } else {
            Write-Err "infrastructure/.env missing and .env.example not found"
            exit 1
        }
    }
}

# =============================================================================
#   CACHE FUNCTIONS
# =============================================================================

function Clear-LaravelCache {
    Write-Info "Clearing Laravel caches..."
    $result = docker exec $Script:ContainerLaravel php artisan optimize:clear 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Laravel caches cleared"
    } else {
        Write-Warn "Could not clear Laravel caches (container may not be running)"
    }
    docker exec $Script:ContainerLaravel php artisan cache:clear 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Success "Application cache cleared" }
}

function Clear-AllCachesBeforeRebuild {
    param([string[]]$ForBuildType = @("laravel"))
    Write-Host ""
    Write-Host "  ===========================================================================" -ForegroundColor Cyan
    Write-Host "   CLEARING ALL CACHES BEFORE REBUILD" -ForegroundColor Cyan
    Write-Host "  ===========================================================================" -ForegroundColor Cyan
    Write-Host ""

    if ($ForBuildType -contains "laravel" -or $ForBuildType -contains "all") {
        $laravelRunning = docker ps --format "{{.Names}}" | Select-String -Pattern $Script:ContainerLaravel -Quiet
        if ($laravelRunning) {
            Write-Info "Clearing Laravel caches in container..."
            docker exec $Script:ContainerLaravel php artisan view:clear 2>&1 | Out-Null
            docker exec $Script:ContainerLaravel php artisan optimize:clear 2>&1 | Out-Null
            docker exec $Script:ContainerLaravel php artisan cache:clear 2>&1 | Out-Null
            Write-Success "Laravel container caches cleared"
        }
        Write-Info "Clearing Laravel cache dirs on host..."
        $viewCache = Join-Path $Script:LaravelPath "storage\framework\views"
        $frameworkCache = Join-Path $Script:LaravelPath "storage\framework\cache"
        $bootstrapCache = Join-Path $Script:LaravelPath "bootstrap\cache"
        foreach ($dir in @($viewCache, $frameworkCache, $bootstrapCache)) {
            if (Test-Path $dir) {
                Get-ChildItem -Path $dir -Recurse -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
                Write-Success "Cleared: $dir"
            }
        }
    }
    Write-Host ""
    Write-Success "All caches cleared - ready for rebuild"
    Write-Host ""
}

function Run-Migrations {
    Write-Info "Checking for pending migrations..."
    $migrateStatus = docker exec $Script:ContainerLaravel php artisan migrate:status 2>&1
    $pendingMigrations = $migrateStatus | Select-String -Pattern "\|\s+No\s+\|" -CaseSensitive
    $pendingCount = ($pendingMigrations | Measure-Object).Count
    if ($pendingCount -gt 0) {
        Write-Warn "Found $pendingCount pending migration(s)"
        Write-Info "Running migrations..."
        docker exec $Script:ContainerLaravel php artisan migrate --force 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) { Write-Success "Database migrations completed" } else { Write-Err "Migration failed" }
    } else {
        Write-Success "Database is up to date"
    }
}

# =============================================================================
#   BUILD FUNCTIONS
# =============================================================================

function Start-DockerBuild {
    param([string]$BuildType, [bool]$UseCache = $true)
    Set-Location $Script:InfraPath
    $envArg = "--env-file", ".env"

    if ($BuildType -eq "all") {
        Clear-AllCachesBeforeRebuild -ForBuildType @("all")
        Write-Step 1 3 "Stopping containers..."
        docker compose $envArg down 2>&1 | Out-Null
        Write-Success "Stopped"
        Write-Step 2 3 "Building ALL containers..."
        Write-Host ""
        $buildArgs = @("compose") + $envArg + @("build")
        if (-not $UseCache) { $buildArgs += "--no-cache" }
        & docker @buildArgs
        $buildResult = $LASTEXITCODE
        Write-Host ""
        if ($buildResult -eq 0) {
            Write-Success "Build complete"
            Write-Step 3 3 "Starting containers..."
            docker compose $envArg up -d
            Write-Host ""
            Write-Info "Waiting for containers to be ready..."
            Start-Sleep -Seconds 8
            Clear-LaravelCache
            Run-Migrations
            Write-Success "All done!"
            return $true
        }
        Write-Err "Build failed"
        return $false
    }

    if ($BuildType -eq "laravel") {
        Clear-AllCachesBeforeRebuild -ForBuildType @("laravel")
        Write-Info "Building Laravel..."
        docker compose $envArg stop laravel 2>&1 | Out-Null
        $buildArgs = @("compose") + $envArg + @("build", "laravel")
        if (-not $UseCache) { $buildArgs += "--no-cache" }
        & docker @buildArgs
        if ($LASTEXITCODE -eq 0) {
            docker compose $envArg up -d laravel
            Start-Sleep -Seconds 5
            Clear-LaravelCache
            Run-Migrations
            Write-Success "Laravel built!"
            return $true
        }
        Write-Err "Laravel build failed"
        return $false
    }

    if ($BuildType -eq "nginx") {
        Write-Info "Building Nginx..."
        docker compose $envArg stop nginx 2>&1 | Out-Null
        $buildArgs = @("compose") + $envArg + @("build", "nginx")
        if (-not $UseCache) { $buildArgs += "--no-cache" }
        & docker @buildArgs
        if ($LASTEXITCODE -eq 0) {
            docker compose $envArg up -d nginx
            Write-Success "Nginx built!"
            return $true
        }
        Write-Err "Nginx build failed"
        return $false
    }

    return $false
}

# =============================================================================
#   MAIN
# =============================================================================

Write-Header

Write-Info "Checking Docker..."
docker info 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Err "Docker not running! Start Docker Desktop."
    if (-not $RebuildAll) { Read-Host "Press Enter to exit" }
    exit 1
}
Write-Success "Docker running"

Ensure-EnvFile

# Enforce PrimaryRules.mdc / ProjectDescription.md: no period/sector building in Vue; logic in Calculator.php
$checkScript = Join-Path $Script:LaravelPath "scripts\check-calculator-architecture.ps1"
if (Test-Path $checkScript) {
    Write-Info "Running calculator architecture check..."
    & $checkScript -RepoRoot $Script:LaravelPath
    if ($LASTEXITCODE -ne 0) {
        Write-Err "Calculator architecture check failed. Fix violations before building."
        if (-not $RebuildAll) { Read-Host "Press Enter to exit" }
        exit 1
    }
    Write-Success "Calculator architecture check passed"
} else {
    Write-Warn "Check script not found: $checkScript (skipping)"
}

# Non-interactive: clear caches + build all + deploy
if ($RebuildAll) {
    Write-Host ""
    Write-Info "Rebuild All (clear caches, build, deploy)..."
    $result = Start-DockerBuild -BuildType "all" -UseCache:$false
    Set-Location $Script:InfraPath
    docker compose --env-file .env ps
    Write-Host ""
    if ($result) { Write-Success "Rebuild and deploy complete." } else { Write-Err "Rebuild failed." }
    Write-Host "   Admin: http://localhost/admin/login (admin@mycities.co.za / admin888)" -ForegroundColor Cyan
    if (-not $RebuildAll) { Read-Host "Press Enter to exit" }
    exit $(if ($result) { 0 } else { 1 })
}

# Show current status
Write-Host ""
Write-Host "   Container Status:" -ForegroundColor Yellow
Set-Location $Script:InfraPath
docker compose --env-file .env ps

# Menu
Write-Host ""
Write-Host "  ===========================================================================" -ForegroundColor Yellow
Write-Host "   BUILD OPTIONS" -ForegroundColor Yellow
Write-Host "  ===========================================================================" -ForegroundColor Yellow
Write-Host ""
Write-Host "   [1] Build ALL (clear caches, stop, build, start)" -ForegroundColor White
Write-Host "   [2] Build LARAVEL only (clear caches first)" -ForegroundColor White
Write-Host "   [3] Build NGINX only" -ForegroundColor White
Write-Host "   [4] Build & start (incremental, use cache)" -ForegroundColor White
Write-Host "   [5] Restart all containers" -ForegroundColor White
Write-Host "   [6] View logs" -ForegroundColor White
Write-Host "   [7] Clear Laravel caches (in container)" -ForegroundColor White
Write-Host "   [8] Clear all caches before rebuild (host + container)" -ForegroundColor White
Write-Host "   [0] Exit" -ForegroundColor Gray
Write-Host ""

$choice = Read-Host "   Choice"

switch ($choice) {
    "1" {
        Write-Host ""
        $result = Start-DockerBuild -BuildType "all" -UseCache:$false
    }
    "2" {
        Write-Host ""
        $result = Start-DockerBuild -BuildType "laravel" -UseCache:$false
    }
    "3" {
        Write-Host ""
        $result = Start-DockerBuild -BuildType "nginx" -UseCache:$false
    }
    "4" {
        Write-Host ""
        Write-Info "Incremental build (using cache)..."
        Set-Location $Script:InfraPath
        docker compose --env-file .env build
        if ($LASTEXITCODE -eq 0) {
            docker compose --env-file .env up -d
            Start-Sleep -Seconds 5
            Clear-LaravelCache
            Run-Migrations
            Write-Success "Build and start complete"
        } else {
            Write-Err "Build failed"
        }
    }
    "5" {
        Write-Host ""
        Write-Info "Restarting containers..."
        Set-Location $Script:InfraPath
        docker compose --env-file .env restart
        Start-Sleep -Seconds 5
        Clear-LaravelCache
        Run-Migrations
        Write-Success "Restarted"
    }
    "6" {
        Write-Host ""
        Write-Host "   [1] Laravel  [2] Nginx  [3] MySQL" -ForegroundColor Gray
        $lc = Read-Host "   Container"
        $name = switch ($lc) {
            "1" { $Script:ContainerLaravel }
            "2" { $Script:ContainerNginx }
            "3" { $Script:ContainerMysql }
            default { $Script:ContainerLaravel }
        }
        docker logs $name --tail 50
    }
    "7" {
        Write-Host ""
        Write-Info "Clearing Laravel caches (in container)..."
        Clear-LaravelCache
        Write-Success "Caches cleared"
    }
    "8" {
        Write-Host ""
        Clear-AllCachesBeforeRebuild -ForBuildType @("all")
        Write-Success "Ready for rebuild. Choose [1] or [2] to build."
    }
    "0" {
        exit 0
    }
    default {
        Write-Err "Invalid choice"
    }
}

# Final status
Write-Host ""
Write-Host "   Final Container Status:" -ForegroundColor Yellow
Set-Location $Script:InfraPath
docker compose --env-file .env ps
Write-Host ""
Write-Host "   Admin: http://localhost/admin/login (admin@mycities.co.za / admin888)" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
