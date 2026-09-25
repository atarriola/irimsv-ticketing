import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';

const appName = import.meta.env.VITE_APP_NAME || 'iRIMSV Ticketing System';

// Live updates ride on a Reverb WebSocket when one is configured; without it, the pages fall back to polling.
if (import.meta.env.VITE_REVERB_APP_KEY) {
    configureEcho({ broadcaster: 'reverb' });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#06b6d4',
    },
});
