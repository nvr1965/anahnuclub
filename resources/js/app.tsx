import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { CallRoot } from './components/video-call';
import { initializeTheme } from './hooks/use-appearance';
import { setUpInstall } from './lib/install-app';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'AnahnuClub';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        syncDocumentLanguage(props.initialPage.props);

        // The call window sits beside the page, so a call carries on from page to page.
        root.render(
            <App {...props}>
                {({ Component, key, props: pageProps }) => (
                    <>
                        <Component key={key} {...pageProps} />
                        <CallRoot />
                    </>
                )}
            </App>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Inertia visits (e.g. switching language) don't reload the page, so keep <html lang/dir> in sync.
function syncDocumentLanguage(pageProps: Record<string, unknown>) {
    const { locale, dir } = pageProps as { locale?: string; dir?: string };
    if (locale) document.documentElement.lang = locale;
    if (dir) document.documentElement.dir = dir;
}

router.on('navigate', (event) => syncDocumentLanguage(event.detail.page.props));
router.on('success', (event) => syncDocumentLanguage(event.detail.page.props));

setUpInstall();

// This will set light / dark mode on load...
initializeTheme();
