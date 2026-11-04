/**
 * Installing the club as an app. Chrome and Edge offer it through an event that comes once,
 * early, so it is caught here at start-up and kept for the "Install the app" menu item.
 */
interface InstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let deferred: InstallPromptEvent | null = null;
const listeners = new Set<() => void>();

/**
 * The service worker's version, kept in step with PUSH_VERSION in public/sw.js. It goes into the
 * worker's address, so a new release is fetched afresh even where a server or phone kept the old
 * file, which left phones without notifications.
 */
export const SW_VERSION = 'push-5';
export const SW_URL = `/sw.js?v=${SW_VERSION}`;

export function setUpInstall() {
    if ('serviceWorker' in navigator && import.meta.env.PROD) {
        window.addEventListener('load', () => navigator.serviceWorker.register(SW_URL).catch(() => undefined));
    }
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event as InstallPromptEvent;
        listeners.forEach((notify) => notify());
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        listeners.forEach((notify) => notify());
    });
}

export function onInstallChange(notify: () => void) {
    listeners.add(notify);
    return () => {
        listeners.delete(notify);
    };
}

/** Already opened as the installed app. */
export function isInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches || (navigator as { standalone?: boolean }).standalone === true;
}

/** iPhone and iPad have no install prompt: the app is added from Safari's Share menu. */
export function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

export function canPrompt() {
    return deferred !== null;
}

export async function promptInstall() {
    if (!deferred) return false;
    const event = deferred;
    deferred = null;
    await event.prompt();
    return (await event.userChoice).outcome === 'accepted';
}

/** Where the club is open, to show the right steps when the browser gives no install prompt. */
export function installPlatform(): 'ios' | 'android' | 'desktop' {
    if (isIos()) return 'ios';
    if (/android/i.test(navigator.userAgent)) return 'android';
    return 'desktop';
}

const HELP_EVENT = 'club:install-help';

/** Opens the steps for adding the club to the home screen (the dialog lives in the top bar). */
export function openInstallHelp() {
    window.dispatchEvent(new Event(HELP_EVENT));
}

export function onInstallHelp(open: () => void) {
    window.addEventListener(HELP_EVENT, open);
    return () => window.removeEventListener(HELP_EVENT, open);
}

/** The menu item's action: the browser's own install prompt when it offers one, otherwise the steps by hand. */
export function installApp() {
    if (canPrompt()) promptInstall();
    else openInstallHelp();
}
