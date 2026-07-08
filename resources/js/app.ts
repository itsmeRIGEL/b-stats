import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, ref } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';

// Global auth error toast state
const showAuthToast = ref(false);
const authToastMessage = ref('');
let authToastTimer: ReturnType<typeof setTimeout> | null = null;

const triggerAuthToast = (msg: string) => {
    authToastMessage.value = msg;
    showAuthToast.value = true;
    if (authToastTimer) clearTimeout(authToastTimer);
    authToastTimer = setTimeout(() => {
        showAuthToast.value = false;
    }, 4000);
};

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () =>
                h('div', [
                    h(App, props),
                    // Global Auth Toast
                    h(
                        'div',
                        {
                            class: [
                                'fixed top-4 right-4 z-[9999] flex items-center gap-3 px-4 py-3 bg-amber-500 text-white rounded-xl shadow-lg shadow-amber-500/20 transition-all duration-300',
                                showAuthToast.value ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-4 pointer-events-none',
                            ].join(' '),
                        },
                        [
                            h('svg', { class: 'w-5 h-5 shrink-0', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
                                h('path', {
                                    'stroke-linecap': 'round',
                                    'stroke-linejoin': 'round',
                                    'stroke-width': '2',
                                    d: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                                }),
                            ]),
                            h('span', { class: 'text-sm font-bold' }, authToastMessage.value),
                        ],
                    ),
                ]),
        });

        app.use(plugin).use(ZiggyVue).mount(el);

        // Listen for auth errors (401/403 responses)
        router.on('error', (event) => {
            const detail = (event as any).detail;
            const status = detail?.status || detail?.response?.status || 0;
            if (status === 401 || status === 403) {
                triggerAuthToast('You must log in first to access this page.');
                setTimeout(() => {
                    router.visit('/');
                }, 2000);
            }
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
