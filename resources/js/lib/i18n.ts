import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Interface strings come from lang/{locale}.json, keyed by the English text.
 * A missing key falls back to the English text itself. ":name" placeholders are replaced.
 */
export function useT() {
    const { translations } = usePage<SharedData>().props;

    return (key: string, replace: Record<string, string | number> = {}) =>
        Object.entries(replace).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), translations?.[key] ?? key);
}
