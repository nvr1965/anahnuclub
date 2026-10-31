import { openChat } from '@/components/chat-dock';
import { PanelMenu, panelMenuItem } from '@/components/notification-bell';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { shortAgo } from '@/lib/relative-time';
import { type Person, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Check, Maximize2, MessageCircleQuestion, Search, SquarePen, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';

interface ChatRow {
    id: number;
    with: Person;
    last: { body: string; mine: boolean; created_at: string | null } | null;
    unread: boolean;
}

type Tab = 'all' | 'unread' | 'requests';

const roundButton =
    'flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 dark:text-neutral-300 dark:hover:bg-neutral-800 [&>svg]:size-5';

/**
 * The Messages tile in the top bar opens the chats in a panel, as Messenger does on Facebook:
 * a search, "All / Unread / Requests", the chats with their last line and age, and a way to the full page.
 */
export function MessengerMenu({ className, children }: { className: string; children: ReactNode }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [chats, setChats] = useState<{ conversations: ChatRow[]; requests: ChatRow[] } | null>(null);
    const [tab, setTab] = useState<Tab>('all');
    const [query, setQuery] = useState('');

    const toggle = async (next: boolean) => {
        setOpen(next);
        if (!next) return;
        try {
            const response = await fetch(route('messages.latest'), { headers: { Accept: 'application/json' } });
            setChats(await response.json());
        } catch {
            setChats({ conversations: [], requests: [] });
        }
    };

    const needle = query.trim().toLocaleLowerCase();
    const rows = (tab === 'requests' ? (chats?.requests ?? []) : (chats?.conversations ?? []))
        .filter((row) => tab !== 'unread' || row.unread)
        .filter((row) => !needle || row.with.name.toLocaleLowerCase().includes(needle) || row.last?.body.toLocaleLowerCase().includes(needle));
    const close = () => setOpen(false);

    return (
        <DropdownMenu open={open} onOpenChange={toggle}>
            <DropdownMenuTrigger className={className} aria-label={t('Messages')}>
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="flex max-h-[calc(100dvh-90px)] w-[min(360px,calc(100vw-16px))] flex-col rounded-xl p-0 shadow-xl"
            >
                <div className="flex items-center justify-between px-4 pt-3 pb-2">
                    <span className="text-2xl font-bold">{t('Chats')}</span>
                    <div className="flex gap-1">
                        <PanelMenu>
                            {(hide) => (
                                <>
                                    <button
                                        type="button"
                                        className={panelMenuItem}
                                        onClick={async () => {
                                            hide();
                                            await fetch(route('messages.read'), {
                                                method: 'POST',
                                                headers: {
                                                    Accept: 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                                                },
                                            });
                                            setChats(
                                                (list) =>
                                                    list && { ...list, conversations: list.conversations.map((c) => ({ ...c, unread: false })) },
                                            );
                                            router.reload({ only: ['counts'] });
                                        }}
                                    >
                                        <Check />
                                        {t('Mark all chats as read')}
                                    </button>
                                    <button
                                        type="button"
                                        className={panelMenuItem}
                                        onClick={() => {
                                            hide();
                                            setTab('requests');
                                        }}
                                    >
                                        <MessageCircleQuestion />
                                        {t('Message requests')}
                                    </button>
                                </>
                            )}
                        </PanelMenu>
                        <Link
                            href={route('messages.index')}
                            onClick={close}
                            className={roundButton}
                            aria-label={t('Open messages')}
                            title={t('Open messages')}
                        >
                            <Maximize2 />
                        </Link>
                        <Link
                            href={route('messages.index')}
                            onClick={close}
                            className={roundButton}
                            aria-label={t('New message')}
                            title={t('New message')}
                        >
                            <SquarePen />
                        </Link>
                    </div>
                </div>

                <label className="mx-4 mb-2 flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 dark:bg-neutral-800">
                    <Search className="text-muted-foreground size-4 shrink-0" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => e.stopPropagation()}
                        placeholder={t('Search messages')}
                        className="min-w-0 flex-1 bg-transparent text-[15px] outline-none"
                    />
                    {query && (
                        <button type="button" onClick={() => setQuery('')} aria-label={t('Clear')} className="text-muted-foreground">
                            <X className="size-4" />
                        </button>
                    )}
                </label>

                <div className="flex gap-1 px-3 pb-1">
                    {(
                        [
                            ['all', t('All'), 0],
                            ['unread', t('Unread'), chats?.conversations.filter((c) => c.unread).length ?? 0],
                            ['requests', t('Requests'), chats?.requests.length ?? 0],
                        ] as [Tab, string, number][]
                    ).map(([key, label, count]) => (
                        <button
                            key={key}
                            type="button"
                            aria-pressed={tab === key}
                            onClick={() => setTab(key)}
                            className="aria-pressed:bg-brand-soft aria-pressed:text-brand dark:aria-pressed:bg-brand/20 rounded-full px-3 py-1.5 text-[15px] font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                        >
                            {label}
                            {count > 0 && key !== 'all' && <span className="ms-1 text-[13px] opacity-80">{count}</span>}
                        </button>
                    ))}
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-1">
                    {chats === null ? (
                        <p className="text-muted-foreground p-6 text-center text-sm">…</p>
                    ) : rows.length === 0 ? (
                        <p className="text-muted-foreground px-3 py-8 text-center text-[15px]">
                            {needle
                                ? t('Nothing found.')
                                : tab === 'requests'
                                  ? t('No requests.')
                                  : tab === 'unread'
                                    ? t('No unread chats.')
                                    : t('No conversations yet.')}
                        </p>
                    ) : (
                        rows.map((row) => (
                            <Link
                                key={row.id}
                                href={route('messages.show', row.id)}
                                onClick={(e) => {
                                    close();
                                    // A plain click opens the chat in a window at the bottom, as on Facebook; a new tab still works.
                                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                                    e.preventDefault();
                                    openChat(row.id, row.with);
                                }}
                                className="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                            >
                                <UserAvatar person={row.with} className="size-14" />
                                <span className="min-w-0 flex-1">
                                    <span dir="auto" className={`block truncate text-[15px] ${row.unread ? 'font-bold' : 'font-medium'}`}>
                                        {row.with.name}
                                    </span>
                                    <span
                                        className={`flex min-w-0 gap-1 text-[13px] ${row.unread ? 'font-semibold text-slate-900 dark:text-white' : 'text-muted-foreground'}`}
                                    >
                                        <span dir="auto" className="min-w-0 truncate">
                                            {row.last && (
                                                <>
                                                    {row.last.mine && `${t('You')}: `}
                                                    {row.last.body}
                                                </>
                                            )}
                                        </span>
                                        {row.last?.created_at && <span className="shrink-0">· {shortAgo(row.last.created_at, locale)}</span>}
                                    </span>
                                </span>
                                {row.unread && <span className="bg-brand size-3 shrink-0 rounded-full" />}
                            </Link>
                        ))
                    )}
                </div>

                <div className="border-t border-slate-200 p-1 dark:border-neutral-800">
                    <Link
                        href={route('messages.index')}
                        onClick={close}
                        className="text-brand block rounded-md p-2 text-center text-[15px] font-medium hover:bg-slate-100 dark:hover:bg-neutral-800"
                    >
                        {t('Open messages')}
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
