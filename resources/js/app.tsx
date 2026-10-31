import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { ChatDock } from './components/chat-dock';
import { ErrorBoundary } from './components/error-boundary';
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

        // The call window and the docked chats sit beside the page, so they carry on from page to page.
        root.render(
            <App {...props}>
                {({ Component, key, props: pageProps }) => (
                    <>
                        <ErrorBoundary resetKey={key}>
                            <Component key={key} {...pageProps} />
                        </ErrorBoundary>
                        <ChatDock />
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

// After an update the old page files are gone; a tab opened before it loads the new ones once.
window.addEventListener('vite:preloadError', (event) => {
    try {
        if (sessionStorage.getItem('reloaded-for-update')) return;
        sessionStorage.setItem('reloaded-for-update', '1');
    } catch {
        // Without storage, reload anyway.
    }
    event.preventDefault();
    window.location.reload();
});
router.on('success', () => {
    try {
        sessionStorage.removeItem('reloaded-for-update');
    } catch {
        // Nothing to clear.
    }
});

setUpInstall();

// This will set light / dark mode on load...
initializeTheme();
