# MyCities-Core

Unified billing and app stack (Laravel + Inertia + Vue). Billing logic lives in a single `Calculator.php` keyed to **ProjectDescription.md**. Fully containerised and portable.

## Docker (deploy — same stack as Cline)

Docker layout is copied from **MyCities-Cline** (Laravel + MySQL + Nginx). No separate frontend; no certbot.

- **Linux:** [infrastructure/DEPLOY.md](infrastructure/DEPLOY.md) — clone, `cd infrastructure`, `cp .env.example .env`, edit `.env`, then `./deploy.sh`. First time: `migrate --force` and `db:seed`; then **/admin/login** — `admin@mycities.co.za` / `admin888`.
- **Windows:** From repo root: `.\BuildDocker_Core.ps1` or `Build_Core.cmd`.

## First steps (done)

- **ProjectDescription.md** – Canonical spec + "Calculator Implementation Checklist". Keep sanity here; if Calculator drifts, fix code to match PD.
- **app/Services/Billing/Calculator.php** – Skeleton with sections/comments in PD order. Implement each TODO per PD.

## Next steps

- Copy migrations, models, and other app files as needed (one by one).
- Implement Calculator.php methods against ProjectDescription.md and the checklist.
