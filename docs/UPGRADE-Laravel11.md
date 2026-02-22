# Laravel 11 + PHP 8.3 Upgrade

This project has been upgraded from Laravel 8 / PHP 8.0 to **Laravel 11** and **PHP 8.3** to align with ProjectDescription.md and modern tooling.

## What Changed

- **PHP:** ^8.3 (Docker: `php:8.3-fpm`)
- **Laravel:** ^11.0
- **CORS:** Removed `fruitcake/laravel-cors`; using Laravel’s built-in `Illuminate\Http\Middleware\HandleCors`
- **Error page (dev):** Replaced `facade/ignition` with `spatie/laravel-ignition` ^2.0
- **Sanctum:** ^4.0 (middleware keys in `config/sanctum.php` updated)
- **Composer dev:** `nunomaduro/collision` ^8.1, `phpunit/phpunit` ^11.0, `laravel/telescope` ^5.0, `laravel/dusk` ^8.0, `laravel/sail` ^1.26

Application structure (Kernel, RouteServiceProvider, bootstrap/app.php) is unchanged; Laravel 11 supports this layout.

## After `composer update`

Run these once after upgrading:

```bash
# Publish Sanctum migrations (L11 no longer auto-loads them)
php artisan vendor:publish --tag=sanctum-migrations

# If you use Telescope, publish its migrations
php artisan vendor:publish --tag=telescope-migrations
```

Then run migrations as usual:

```bash
php artisan migrate
```

## Docker

Rebuild the stack so the PHP 8.3 image and new dependencies are used:

```bash
cd infrastructure
docker-compose build --no-cache
docker-compose up -d
```

Or from repo root: `./BuildDocker_Core.ps1` (Windows) or `./infrastructure/deploy.sh` (Linux).

## Optional

- **Schema squash:** If you have many migrations and use column `change()`, consider squashing: `php artisan schema:dump` then run migrations.
- **Carbon 3:** Laravel 11 supports both Carbon 2 and 3; upgrading to Carbon 3 is optional and has its own breaking changes.
