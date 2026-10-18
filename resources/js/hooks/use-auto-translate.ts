import { type Translatable } from '@/types';
import { useEffect, useRef, useState } from 'react';

type Key = string;
type Fields = Record<string, string>;

const keyOf = (type: string, id: number): Key => `${type}:${id}`;

/** How often and how long the page asks again for translations still being made after publishing. */
const RETRY_MS = 3000;
const MAX_ATTEMPTS = 40;

/**
 * Fetches translations the page did not get with the feed, a few items per request,
 * and asks again until they are there, so a fresh post's translation appears on its own.
 * Returns them keyed by "type:id".
 */
export function useAutoTranslate(items: Translatable[], locale: string) {
    const [translated, setTranslated] = useState<Record<Key, Fields>>({});
    const requested = useRef(new Set<Key>());

    useEffect(() => {
        requested.current.clear();
        setTranslated({});
    }, [locale]);

    useEffect(() => {
        let waiting = items.filter((item) => item.translation?.pending && !requested.current.has(keyOf(item.translation.type, item.id)));
        if (waiting.length === 0) return;

        waiting.forEach((item) => requested.current.add(keyOf(item.translation!.type, item.id)));
        let cancelled = false;
        let timer: ReturnType<typeof setTimeout> | undefined;
        let attempts = 0;

        const round = async () => {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const found = new Set<Key>();
            for (let i = 0; i < waiting.length && !cancelled; i += 5) {
                const chunk = waiting.slice(i, i + 5).map((item) => ({ type: item.translation!.type, id: item.id }));
                try {
                    const response = await fetch(route('translations'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ items: chunk }),
                    });
                    if (!response.ok) continue;
                    const data: { results: { type: string; id: number; fields: Fields }[] } = await response.json();
                    if (cancelled) return;
                    const ready = data.results.filter((r) => Object.keys(r.fields).length > 0);
                    ready.forEach((r) => found.add(keyOf(r.type, r.id)));
                    if (ready.length > 0) {
                        setTranslated((current) => {
                            const next = { ...current };
                            ready.forEach((r) => (next[keyOf(r.type, r.id)] = r.fields));
                            return next;
                        });
                    }
                } catch {
                    // Network trouble: the original text stays on screen.
                }
            }
            waiting = waiting.filter((item) => !found.has(keyOf(item.translation!.type, item.id)));
            if (!cancelled && waiting.length > 0 && ++attempts < MAX_ATTEMPTS) {
                timer = setTimeout(round, RETRY_MS);
            }
        };
        round();

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [items, locale]);

    return (type: string, id: number): Fields | undefined => translated[keyOf(type, id)];
}
