/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { AccessibilityMenu } from './components/accessibility-menu';
import { ChatDock } from './components/chat-dock';
import { ErrorBoundary } from './components/error-boundary';
import { CallRoot } from './components/video-call';
import { initializeTheme } from './hooks/use-appearance';
import { initializeAccessibility } from './lib/accessibility';
import { keepInterfaceStrings } from './lib/i18n';
import { setUpInstall } from './lib/install-app';
import { listenForPush, syncPush } from './lib/push';
import { type SharedData } from './types';

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
        keepInterfaceStrings(props.initialPage.props as unknown as Partial<SharedData>);
        syncPush(Boolean((props.initialPage.props as unknown as Partial<SharedData>).auth?.user)).catch(() => undefined);

        // The call window and the docked chats sit beside the page, so they carry on from page to page.
        root.render(
            <App {...props}>
                {({ Component, key, props: pageProps }) => (
                    <>
                        {/* For the keyboard: the first Tab offers to jump over the menus straight to the page's content. */}
                        <a
                            href="#main"
                            onClick={(e) => {
                                const main = document.querySelector('main');
                                if (!main) return;
                                e.preventDefault();
                                main.setAttribute('tabindex', '-1');
                                main.focus();
                            }}
                            className="sr-only focus:not-sr-only focus:fixed focus:start-3 focus:top-3 focus:z-[100] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-black focus:shadow-lg"
                        >
                            {skipLabel(props.initialPage.props)}
                        </a>
                        <ErrorBoundary resetKey={key}>
                            <Component key={key} {...pageProps} />
                        </ErrorBoundary>
                        <ChatDock />
                        <CallRoot />
                        <AccessibilityMenu />
                    </>
                )}
            </App>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// The skip link's words, from the interface strings the first page brought.
function skipLabel(pageProps: Record<string, unknown>) {
    const strings = (pageProps as { translations?: Record<string, string> | null }).translations;
    return strings?.['Skip to content'] ?? 'Skip to content';
}

// Inertia visits (e.g. switching language or style) don't reload the page, so keep <html lang/dir/data-skin> in sync.
function syncDocumentLanguage(pageProps: Record<string, unknown>) {
    const { locale, dir, skin } = pageProps as { locale?: string; dir?: string; skin?: string };
    if (locale) document.documentElement.lang = locale;
    if (dir) document.documentElement.dir = dir;
    if (skin) document.documentElement.dataset.skin = skin;
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
listenForPush();

// This will set light / dark mode on load...
initializeTheme();

// The accessibility menu's settings (text size, palette…), kept in this browser.
initializeAccessibility();
