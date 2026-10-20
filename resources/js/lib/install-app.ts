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

export function setUpInstall() {
    if ('serviceWorker' in navigator && import.meta.env.PROD) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => undefined));
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
