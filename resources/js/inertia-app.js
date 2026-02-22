/**
 * Inertia + Vue 3 entry point (Vite / @inertiajs/vue3 v1.x).
 * Used exclusively by resources/views/app.blade.php.
 * Legacy Blade pages use app.js instead.
 *
 * =========================================================
 * CRITICAL - DO NOT CHANGE setup() WITHOUT READING THIS
 * =========================================================
 *
 * CORRECT:   createApp({ render: () => h(App, props) }).use(plugin).mount(el)
 * WRONG:     createApp(App).use(plugin).mount(el)   <- never do this
 *
 * Why: @inertiajs/vue3 v1.x passes initialPage and resolveComponent via
 * `props`. Without h(App, props), the App component receives no initialPage,
 * router.init() is called with initialPage=undefined, and the page throws:
 *   "TypeError: Cannot read properties of undefined (reading 'url')"
 *
 * This produces a blank screen with no obvious cause. See:
 *   docs/CriticalFixProcedures.md - FIX-001
 *
 * Ziggy (route() helper):
 *   @routes in app.blade.php sets window.Ziggy with route definitions.
 *   ZiggyVue plugin (from ziggy-js npm package) reads window.Ziggy and
 *   exposes route() to all Vue templates. See FIX-005.
 */
import './bootstrap';
import { createApp, h, defineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

// ---------------------------------------------------------------------------
// Regression fallback: shown in the browser if initialPage is ever missing.
// Makes the blank-screen bug instantly visible without needing DevTools.
// ---------------------------------------------------------------------------
const InertiaSetupError = defineComponent({
    name: 'InertiaSetupError',
    props: { message: String },
    template: `
        <div style="font-family:monospace;padding:2rem;background:#fff3cd;border:2px solid #ffc107;border-radius:8px;max-width:780px;margin:4rem auto;">
            <h2 style="color:#856404;margin:0 0 1rem">&#9888; Inertia initialPage missing â€” FIX-001</h2>
            <p style="color:#533f03">
                <code>props.initialPage</code> is <strong>undefined</strong>. The most common cause is
                that <code>setup()</code> in <code>inertia-app.js</code> was changed from:
            </p>
            <pre style="background:#f8f9fa;padding:1rem;border-radius:4px;color:#d63384">createApp(App).use(plugin).mount(el)  // WRONG</pre>
            <p style="color:#533f03">back to the correct form:</p>
            <pre style="background:#f8f9fa;padding:1rem;border-radius:4px;color:#198754">createApp({ render: () => h(App, props) }).use(plugin).mount(el)  // CORRECT</pre>
            <p style="color:#533f03;margin-top:1rem">
                See <strong>docs/CriticalFixProcedures.md &mdash; FIX-001</strong> for full details.
                After fixing, run <code>npm run build</code> and rebuild Docker.
            </p>
        </div>
    `,
});

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    resolve: async (name) => {
        const path = `./Pages/${name}.vue`;
        const loader = pages[path];
        if (!loader) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        return (await loader()).default;
    },

    // WARNING: keep h(App, props) - see header comment above.
    setup({ el, App, props, plugin }) {
        // Guard: if initialPage is missing, render a visible fallback instead
        // of a silent blank screen. This catches the FIX-001 regression
        // immediately in the browser without needing DevTools open.
        if (!props?.initialPage) {
            console.error('[Inertia FIX-001] props.initialPage is undefined. See docs/CriticalFixProcedures.md');
            createApp(InertiaSetupError).mount(el);
            return;
        }
        // Mount the app.
        // ZiggyVue reads window.Ziggy (set by @routes Blade directive) and
        // makes route('name') available in all Vue templates.
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin).use(ZiggyVue).mount(el);
    },

    title: (title) => (title ? `${title} - MyCities-Core` : 'MyCities-Core'),
});
