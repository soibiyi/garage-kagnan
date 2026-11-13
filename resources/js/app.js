import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Garage Kagnan';

// ─── Erreurs du navigateur → journal des erreurs du super administrateur ───
// (une fois par erreur distincte, 10 au plus par page, jamais bloquant)
const erreursSignalees = new Set();
function signalerErreur(err, origine = 'JavaScript') {
    try {
        const message = String(err?.message ?? err ?? 'Erreur inconnue').slice(0, 2000);
        const cle = `${origine}|${message}`;
        if (erreursSignalees.has(cle) || erreursSignalees.size >= 10) return;
        erreursSignalees.add(cle);
        const lignePile = String(err?.stack ?? '').split('\n').find((l) => l.includes('http')) ?? '';
        const position = lignePile.match(/(https?:\/\/[^\s)]+):(\d+):\d+/);
        window.axios?.post('/erreurs-client', {
            message,
            type: `${err?.name ?? 'Error'} · ${origine}`,
            fichier: position?.[1] ?? null,
            ligne: position ? Number(position[2]) : null,
            trace: err?.stack ? String(err.stack).slice(0, 20000) : null,
            url: window.location.href,
        }).catch(() => {});
    } catch {
        // Le signalement ne doit jamais provoquer d'erreur lui-même
    }
}
window.addEventListener('error', (e) => signalerErreur(e.error ?? e.message, 'JavaScript'));
window.addEventListener('unhandledrejection', (e) => {
    // Les erreurs HTTP (validation, 403…) sont déjà gérées côté serveur : on ne garde que les vraies erreurs JS
    if (e.reason?.isAxiosError) return;
    signalerErreur(e.reason, 'Promesse');
});

createInertiaApp({
    title: (title) =>
        title
            ? title.toLowerCase().includes('garage kagnan')
                ? title
                : `${title} - ${appName}`
            : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob(['./Pages/**/*.vue', '!./Pages/EspaceClient/sections/**']),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = (err, _instance, info) => {
            console.error(err);
            signalerErreur(err, `Vue (${info})`);
        };
        return app.use(plugin).use(ZiggyVue).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
