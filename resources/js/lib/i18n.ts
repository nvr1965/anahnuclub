import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';

/**
 * Interface strings come from lang/{locale}.json, keyed by the English text.
 * A missing key falls back to the English text itself. ":name" placeholders are replaced.
 *
 * The first page brings all the strings; the page keeps them and tells the server which version it has,
 * so the next pages come without them (translations: null) unless they changed, e.g. another language.
 */
let known: { version: string; strings: Record<string, string> } | null = null;

function remember(props: Partial<SharedData>) {
    if (props.translations && props.translations_version) known = { version: props.translations_version, strings: props.translations };
}

/** Called once at start-up: keeps the strings of each page and names the kept version on each visit. */
export function keepInterfaceStrings(initialProps: Partial<SharedData>) {
    remember(initialProps);
    router.on('before', (event) => {
        if (known) event.detail.visit.headers = { ...event.detail.visit.headers, 'X-UI-Strings': known.version };
    });
    router.on('success', (event) => remember(event.detail.page.props as Partial<SharedData>));
}

export function useT() {
    const { translations } = usePage<SharedData>().props;
    const strings = translations ?? known?.strings;

    return (key: string, replace: Record<string, string | number> = {}) =>
        Object.entries(replace).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), strings?.[key] ?? key);
}
