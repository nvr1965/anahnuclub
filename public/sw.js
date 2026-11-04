// Anahnu Club as an installed app. Pages always come from the network (the club is live and
// personal); the built scripts and styles, whose names change with every release, are kept on the
// phone so the app opens fast; with no connection a page shows the offline notice.
const CACHE = 'anahnu-v3';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then(pageNotData)
                .catch(() => caches.match(OFFLINE)),
        );
        return;
    }

    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
});

// A page and the data the page asks for share one address. If the browser cache hands back the data
// (after a restored tab or going back), the page is fetched afresh from the server instead.
function pageNotData(response) {
    const type = response.headers.get('Content-Type') || '';
    if (response.headers.has('X-Inertia') || type.includes('application/json')) {
        return fetch(response.url, { cache: 'reload', credentials: 'same-origin' });
    }
    return response;
}

// Notifications (Web Push): a new message or an incoming call wakes the phone even with the site closed.
// With the club open and in front of the member, the page just updates at once instead.
// Which version of this file the phone runs: "Check notifications" asks, to spot a phone on an old one.
// Keep in step with SW_VERSION in resources/js/lib/install-app.ts.
const PUSH_VERSION = 'push-5';
self.addEventListener('message', (event) => {
    if (event.data !== 'push-version') return;
    const answer = { type: 'push-version', version: PUSH_VERSION };
    if (event.ports?.[0]) event.ports[0].postMessage(answer);
    else event.source?.postMessage(answer);
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            windows.forEach((client) => client.postMessage({ type: 'push', data }));
            const watching = windows.some((client) => client.visibilityState === 'visible' && client.focused);
            // A test from "Check notifications" shows even on the screen it was asked from.
            if (watching && data.kind !== 'test') return undefined;
            return self.registration.showNotification(data.title || 'Anahnu Club', {
                body: data.body || '',
                icon: data.icon || '/icons/icon-192.png',
                badge: '/icons/icon-192.png',
                tag: data.tag,
                renotify: Boolean(data.tag),
                requireInteraction: data.kind === 'call',
                vibrate: data.kind === 'call' ? [800, 400, 800, 400, 800, 400, 800] : [200],
                // A call gets "Answer" and "Decline" buttons right on the notification.
                actions: data.actions ? Object.entries(data.actions).map(([action, title]) => ({ action, title })) : [],
                data: { url: data.url || '/messages', decline: data.decline },
            });
        }),
    );
});

// Tapping a notification opens that chat, in the club's window if one is open.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    // "Decline" on a call: the caller hears it is declined, and nothing opens.
    if (event.action === 'decline' && event.notification.data?.decline) {
        event.waitUntil(
            fetch(event.notification.data.decline, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(
                () => undefined,
            ),
        );
        return;
    }
    const url = new URL(event.notification.data?.url || '/messages', self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => new URL(client.url).origin === self.location.origin);
            if (open) return open.focus().then((client) => (client || open).navigate(url));
            return self.clients.openWindow(url);
        }),
    );
});

// The browser renewed the subscription by itself: tell the site the new address.
self.addEventListener('pushsubscriptionchange', (event) => {
    const options = event.oldSubscription?.options;
    if (!options) return;
    event.waitUntil(
        self.registration.pushManager.subscribe(options).then((subscription) =>
            fetch('/push/subscriptions/renew', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ old: event.oldSubscription.endpoint, subscription: subscription.toJSON() }),
            }),
        ),
    );
});
