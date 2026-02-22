# MyCities-Core

Unified billing and app stack (Laravel + Inertia + Vue). Billing logic lives in a single `Calculator.php` keyed to **ProjectDescription.md**.

## First steps (done)

- **ProjectDescription.md** – Canonical spec + "Calculator Implementation Checklist". Keep sanity here; if Calculator drifts, fix code to match PD.
- **app/Services/Billing/Calculator.php** – Skeleton with sections/comments in PD order. Implement each TODO per PD.

## Next steps

- Copy migrations, models, and other app files as needed (one by one).
- Implement Calculator.php methods against ProjectDescription.md and the checklist.
