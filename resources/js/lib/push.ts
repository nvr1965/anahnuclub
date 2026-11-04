import { isInstalled, isIos, SW_URL, SW_VERSION } from './install-app';

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

/** The service worker, brought up to this release's version first (it then controls the page). */
async function registration() {
    const current = await navigator.serviceWorker.getRegistration();
    if (current?.active && (await workerVersion(current.active)) === SW_VERSION) return current;
    const reg = await navigator.serviceWorker.register(SW_URL);
    await activated(reg);
    return reg;
}

/** Waits (up to 15 seconds) until the newly fetched worker takes over from the old one. */
function activated(reg: ServiceWorkerRegistration): Promise<void> {
    const worker = reg.installing ?? reg.waiting;
    if (!worker) return Promise.resolve();
    return new Promise((resolve) => {
        const timer = window.setTimeout(resolve, 15000);
        const check = () => {
            if (worker.state === 'activated' || worker.state === 'redundant') {
                window.clearTimeout(timer);
                worker.removeEventListener('statechange', check);
                resolve();
            }
        };
        worker.addEventListener('statechange', check);
        check();
    });
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
    /** The service worker's version on this device, null when it does not answer (an old one). */
    version?: string | null;
    /** The test reached this device's service worker within a few seconds. */
    arrived?: boolean;
    /** The member's last pushes before this test: kind, the push service's answer (or error), time. */
    recent?: { kind: string; result: string; at: string }[];
    /** The device could show a notification made right here: '' when it could, otherwise the browser's error. */
    local?: string;
}

/** Asks a service worker its version; null from an old one, which does not answer. */
function workerVersion(worker: ServiceWorker | null | undefined): Promise<string | null> {
    return new Promise((resolve) => {
        if (!worker) return resolve(null);
        const channel = new MessageChannel();
        const timer = window.setTimeout(() => resolve(null), 1500);
        channel.port1.onmessage = (event) => {
            window.clearTimeout(timer);
            resolve(event.data?.version ?? null);
        };
        worker.postMessage('push-version', [channel.port2]);
    });
}

function testArrives(ms: number): Promise<boolean> {
    return new Promise((resolve) => {
        const timer = window.setTimeout(() => done(false), ms);
        const onPush = (event: Event) => (event as CustomEvent).detail?.kind === 'test' && done(true);
        function done(arrived: boolean) {
            window.clearTimeout(timer);
            window.removeEventListener('club:push', onPush);
            resolve(arrived);
        }
        window.addEventListener('club:push', onPush);
    });
}

/**
 * "Check notifications": signs this device up if needed, sends a test to all the member's devices,
 * and finds where it stops: at the push service, on the way to this phone, or when the phone shows it.
 */
export async function testPush(): Promise<PushReport> {
    const state = await enablePush();
    if (state !== 'on') return { state, devices: [] };
    const version = await workerVersion((await registration()).active);
    const arrival = testArrives(10000);
    const response = await fetch(route('push.test'), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
    const { devices, recent } = (await response.json()) as { devices: PushReport['devices']; recent: PushReport['recent'] };
    const arrived = await arrival;
    let local = '';
    try {
        await (await registration()).showNotification('Anahnu Club', { body: 'OK', tag: 'local-test', icon: '/icons/icon-192.png' });
    } catch (error) {
        local = error instanceof Error ? error.message : String(error);
    }
    return { state, devices, version, arrived, local, recent };
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
    navigator.serviceWorker.startMessages();
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
