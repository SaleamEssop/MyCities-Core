# MyCities-Core — Deploy (same stack as Cline: Laravel + MySQL + Nginx)

Docker layout and build are copied from **MyCities-Cline** (working implementation). Only Laravel + MySQL + Nginx; no separate frontend container, no certbot.

## Prerequisites

- Docker and Docker Compose on the server (Linux) or host (Windows).
- No PHP/Node/MySQL on host.

## Linux (via git)

### 1. Clone

```bash
git clone <repo-url> MyCities-Core
cd MyCities-Core/infrastructure
```

### 2. Environment

```bash
cp .env.example .env
```

Edit `.env`: set **APP_URL**; set **APP_KEY** (run `docker exec mycities-core-laravel php artisan key:generate --show` after first up, then add to `.env` and recreate: `docker-compose up -d --force-recreate laravel`); set **DB_PASSWORD** / **DB_ROOT_PASSWORD** for production.

### 3. Build and start

```bash
docker-compose --env-file .env build --no-cache
docker-compose --env-file .env up -d
```

Or from repo root: run `./infrastructure/deploy.sh` (after `chmod +x`).

### 4. Migrate and seed (first time)

```bash
docker exec mycities-core-laravel php artisan migrate --force
docker exec mycities-core-laravel php artisan db:seed
```

### 5. Admin panel

- Open **APP_URL** in browser → **/admin** or **/admin/login**
- Login: `admin@mycities.co.za` / `admin888`

## Windows

From repo root: `.\BuildDocker_Core.ps1` or `Build_Core.cmd`

## Stack

| Service  | Container             |
|----------|------------------------|
| mysql    | mycities-core-mysql   |
| laravel  | mycities-core-laravel|
| nginx    | mycities-core-nginx   |

Network: `mycities-core-network`. For HTTPS, put a reverse proxy in front.
