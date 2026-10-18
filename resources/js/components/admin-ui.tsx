import { useT } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type ReactNode, useState } from 'react';

export interface Page<T> {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    from: number | null;
    to: number | null;
}

export const card = 'rounded-lg bg-white shadow-sm dark:bg-neutral-900';

/** Search box that keeps the other query parameters of the current list. */
export function AdminSearch({ value, routeName, params = {} }: { value: string; routeName: string; params?: Record<string, unknown> }) {
    const t = useT();
    const [q, setQ] = useState(value);

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                router.get(route(routeName), { ...params, q: q.trim() || undefined }, { preserveState: true });
            }}
            className="flex items-center gap-2 rounded-full bg-white px-4 py-2 shadow-sm dark:bg-neutral-900"
        >
            <Search className="text-muted-foreground size-4" />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('Search')} className="w-56 bg-transparent text-sm outline-none" />
        </form>
    );
}

export function Pager({ page }: { page: Page<unknown> }) {
    const t = useT();
    if (!page.prev_page_url && !page.next_page_url) return null;

    return (
        <nav className="text-brand mt-4 flex items-center justify-between text-sm font-medium">
            {page.prev_page_url ? <Link href={page.prev_page_url} preserveScroll>{t('Previous')}</Link> : <span />}
            <span className="text-muted-foreground">
                {page.from}–{page.to} / {page.total}
            </span>
            {page.next_page_url ? <Link href={page.next_page_url} preserveScroll>{t('Next')}</Link> : <span />}
        </nav>
    );
}

export function Badge({ children, tone = 'gray' }: { children: ReactNode; tone?: 'gray' | 'red' | 'green' | 'brand' }) {
    const tones = {
        gray: 'bg-slate-100 text-slate-600 dark:bg-neutral-800 dark:text-neutral-300',
        red: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
        green: 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
        brand: 'bg-brand-soft text-brand',
    };
    return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${tones[tone]}`}>{children}</span>;
}

export function ActionButton({ children, onClick, danger = false }: { children: ReactNode; onClick: () => void; danger?: boolean }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-md px-2.5 py-1 text-xs font-medium ${danger ? 'text-red-600 hover:bg-red-50 dark:hover:bg-red-950' : 'text-brand hover:bg-brand-soft'}`}
        >
            {children}
        </button>
    );
}

/** Sends a change and keeps the list where it was. */
export function send(method: 'patch' | 'post' | 'delete', url: string, data: Record<string, unknown> = {}) {
    const options = { preserveScroll: true, preserveState: true };
    if (method === 'delete') router.delete(url, { ...options, data });
    else router[method](url, data, options);
}
