import { type Translatable } from '@/types';
import { useEffect, useRef, useState } from 'react';

type Key = string;
type Fields = Record<string, string>;

const keyOf = (type: string, id: number): Key => `${type}:${id}`;

/**
 * Fetches translations the server did not have cached yet, a few items per request,
 * and returns them keyed by "type:id". Items already translated are not requested again.
 */
export function useAutoTranslate(items: Translatable[], locale: string) {
    const [translated, setTranslated] = useState<Record<Key, Fields>>({});
    const requested = useRef(new Set<Key>());

    useEffect(() => {
        requested.current.clear();
        setTranslated({});
    }, [locale]);

    useEffect(() => {
        const pending = items.filter((item) => item.translation?.pending && !requested.current.has(keyOf(item.translation.type, item.id)));
        if (pending.length === 0) return;

        pending.forEach((item) => requested.current.add(keyOf(item.translation!.type, item.id)));
        let cancelled = false;

        (async () => {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            for (let i = 0; i < pending.length && !cancelled; i += 5) {
                const chunk = pending.slice(i, i + 5).map((item) => ({ type: item.translation!.type, id: item.id }));
                try {
                    const response = await fetch(route('translations'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ items: chunk }),
                    });
                    if (!response.ok) continue;
                    const data: { results: { type: string; id: number; fields: Fields }[] } = await response.json();
                    if (cancelled) return;
                    setTranslated((current) => {
                        const next = { ...current };
                        data.results.forEach((r) => (next[keyOf(r.type, r.id)] = r.fields));
                        return next;
                    });
                } catch {
                    // Network trouble: the original text stays on screen.
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [items, locale]);

    return (type: string, id: number): Fields | undefined => translated[keyOf(type, id)];
}
