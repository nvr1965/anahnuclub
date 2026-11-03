import { isInstalled, isIos } from './install-app';

/**
 * Notifications on this device (Web Push). The browser keeps one connection to its push service
 * (Google, Apple, Mozilla), the way WhatsApp and Telegram do, so a new message or call reaches
 * the phone even with the club closed. The service worker (public/sw.js) shows it; with the club
 * open it tells the page instead, and the page fetches the new message at once.
 */
export type PushState = 'unsupported' | 'ios-install' | 'denied' | 'off' | 'on';

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

function supported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

async function registration() {
    return (await navigator.serviceWorker.getRegistration()) ?? navigator.serviceWorker.register('/sw.js');
}

export async function pushState(): Promise<PushState> {
    // An iPhone gets notifications only from the club added to the Home Screen.
    if (isIos() && !isInstalled()) return 'ios-install';
    if (!supported()) return 'unsupported';
    if (Notification.permission === 'denied') return 'denied';
    if (Notification.permission !== 'granted') return 'off';
    const subscription = await (await registration()).pushManager.getSubscription();
    return subscription ? 'on' : 'off';
}

function toBytes(base64url: string) {
    const raw = atob(base64url.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (base64url.length % 4)) % 4));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

async function save(subscription: PushSubscription) {
    await fetch(route('push.store'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(subscription.toJSON()),
    });
}

/** Asks the member's permission and signs this device up. */
export async function enablePush(): Promise<PushState> {
    if (!supported()) return pushState();
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return pushState();
    const reg = await registration();
    const { key } = (await (await fetch(route('push.key'), { headers: { Accept: 'application/json' } })).json()) as { key: string };
    let subscription = await reg.pushManager.getSubscription();
    // Signed up earlier with another key (the site's key changed): start over.
    const current = subscription?.options.applicationServerKey;
    if (subscription && current && btoa(String.fromCharCode(...new Uint8Array(current))) !== btoa(String.fromCharCode(...toBytes(key)))) {
        await subscription.unsubscribe();
        subscription = null;
    }
    subscription ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toBytes(key) });
    await save(subscription);
    return 'on';
}

export interface PushReport {
    state: PushState;
    /** Each of the member's devices and what its push service answered (201 = taken). */
    devices: { service: string; status: number }[];
}

/** "Check notifications": signs this device up if needed and sends a test to all the member's devices. */
export async function testPush(): Promise<PushReport> {
    const state = await enablePush();
    if (state !== 'on') return { state, devices: [] };
    const response = await fetch(route('push.test'), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
    const { devices } = (await response.json()) as { devices: PushReport['devices'] };
    return { state, devices };
}

export async function disablePush(): Promise<PushState> {
    const subscription = supported() ? await (await registration()).pushManager.getSubscription() : null;
    if (subscription) {
        await fetch(route('push.destroy'), {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ endpoint: subscription.endpoint }),
        }).catch(() => undefined);
        await subscription.unsubscribe();
    }
    return pushState();
}

/**
 * At start-up: a device already signed up is reported again (it may be a new member on a shared
 * computer, or the server lost it), and one signed out stops getting the previous member's messages.
 */
export async function syncPush(signedIn: boolean) {
    if (!supported() || Notification.permission !== 'granted') return;
    const reg = await navigator.serviceWorker.getRegistration();
    const subscription = await reg?.pushManager.getSubscription();
    if (!subscription) return;
    if (signedIn) await save(subscription).catch(() => undefined);
    else await subscription.unsubscribe();
}

/** The service worker passes on each push while the club is open: "club:push" lets the pages react at once. */
export function listenForPush() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'push') window.dispatchEvent(new CustomEvent('club:push', { detail: event.data.data }));
    });
}

/**
 * Runs $refresh when something new may have come: a push arrived, the tab came back to the front,
 * or the connection came back after being lost.
 */
export function onFreshNews(refresh: (detail?: { kind?: string; conversation?: number }) => void) {
    const onPush = (event: Event) => refresh((event as CustomEvent).detail);
    const onVisible = () => document.visibilityState === 'visible' && refresh();
    const onOnline = () => refresh();
    window.addEventListener('club:push', onPush);
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('online', onOnline);
    return () => {
        window.removeEventListener('club:push', onPush);
        document.removeEventListener('visibilitychange', onVisible);
        window.removeEventListener('online', onOnline);
    };
}
