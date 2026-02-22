# Inertia + Vue 3 Setup (MyCities-Core)

## What was added

- **Composer:** `inertiajs/inertia-laravel`
- **NPM:** `@inertiajs/vue3`
- **Middleware:** `App\Http\Middleware\HandleInertiaRequests` (registered in `web` group)
- **Root view:** `resources/views/app.blade.php` (loads `js/inertia-app.js`)
- **Entry:** `resources/js/inertia-app.js` (createInertiaApp + Vue 3)
- **Pages:** `resources/js/Pages/Welcome.vue` (example)
- **Route:** `GET /inertia-welcome` → `Inertia::render('Welcome')`
- **Mix:** second entry `inertia-app.js` in `webpack.mix.js`

Legacy Blade pages and existing Vue mount points still use `app.js`; only routes that return `Inertia::render(...)` use the new app.

## Commands to run

From project root (or inside Docker with PHP/Composer and Node):

```bash
composer install
npm install
npm run dev
```

Then open `/inertia-welcome` to verify Inertia + Vue 3.

## Next: add more Inertia pages

1. Create `resources/js/Pages/Admin/Dashboard.vue` (or similar).
2. In a controller: `return Inertia::render('Admin/Dashboard', ['data' => ...]);`
3. Route in `web.php` (inside auth middleware as needed).

## Quasar (optional)

To add Quasar for UI components: `npm install quasar`, then in `inertia-app.js` register Quasar plugin and use Quasar components in Pages. See Quasar Vue 3 docs.
