import '../css/app.css';
import './echo';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { initializeTelegramMiniApp } from './composables/useTelegramMiniApp';
import i18n from './i18n';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Determine locale: localStorage takes priority, then server, then default
        const storedLocale =
            typeof window !== 'undefined'
                ? localStorage.getItem('locale')
                : null;
        const serverLocale = props.initialPage.props.locale as
            | string
            | undefined;
        const locale =
            storedLocale === 'ar' || storedLocale === 'en'
                ? storedLocale
                : serverLocale === 'ar' || serverLocale === 'en'
                  ? serverLocale
                  : 'ar';

        i18n.global.locale.value = locale;
        document.documentElement.lang = locale;
        document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n);

        app.mount(el);

        // Initialize Telegram Mini App and auto-authenticate if needed
        const isAuthenticated = !!props.initialPage.props.auth?.user;
        initializeTelegramMiniApp(isAuthenticated).then((result) => {
            if (result?.redirect_url) {
                window.location.href = result.redirect_url;
            }
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
