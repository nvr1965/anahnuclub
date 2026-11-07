import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/** What the "+" menu of the top bar can start, and the page where each is made. */
export type CreateKind = 'post' | 'moment' | 'clip' | 'video' | 'event' | 'group';

const pages: Record<CreateKind, string> = {
    post: 'dashboard',
    moment: 'dashboard',
    clip: 'dashboard',
    video: 'videos.index',
    event: 'events.index',
    group: 'groups.index',
};

const EVENT = 'club:create';

/** On its page already: the form opens at once. Elsewhere the page opens with ?create=… and opens it. */
export function startCreating(kind: CreateKind) {
    const target = route(pages[kind]);
    if (new URL(target, window.location.href).pathname === window.location.pathname) {
        window.dispatchEvent(new CustomEvent<CreateKind>(EVENT, { detail: kind }));
    } else {
        router.visit(route(pages[kind], { create: kind }));
    }
}

/** Opens a form when the "+" menu asks for it, now or by the address the page was opened with. */
export function useCreateRequest(kind: CreateKind, open: () => void) {
    const latest = useRef(open);
    latest.current = open;

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('create') === kind) {
            // Once: a reload or the way back does not open it again.
            params.delete('create');
            const query = params.toString();
            window.history.replaceState(window.history.state, '', window.location.pathname + (query ? `?${query}` : '') + window.location.hash);
            latest.current();
        }
        const listen = (e: Event) => (e as CustomEvent<CreateKind>).detail === kind && latest.current();
        window.addEventListener(EVENT, listen);
        return () => window.removeEventListener(EVENT, listen);
    }, [kind]);
}
