import { type ClubNotification, NotificationList } from '@/components/notification-list';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Check, Ellipsis, Settings2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';

/** The "…" beside a panel's title: a small menu of its own, opened in place. */
export function PanelMenu({ children }: { children: (close: () => void) => ReactNode }) {
    const t = useT();
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                aria-label={t('More')}
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            >
                <Ellipsis className="size-5" />
            </button>
            {open && (
                <div className="absolute end-0 top-10 z-10 w-64 rounded-lg bg-white p-1.5 shadow-lg ring-1 ring-black/5 dark:bg-neutral-900 dark:ring-white/10">
                    {children(() => setOpen(false))}
                </div>
            )}
        </div>
    );
}

export const panelMenuItem =
    'flex w-full items-center gap-3 rounded-md px-2 py-2 text-start text-[15px] font-medium hover:bg-slate-100 dark:hover:bg-neutral-800 [&>svg]:size-5';

/** The bell in the top bar (its look comes from the children): opening it loads the latest notices, as on Facebook. */
export function NotificationBell({ className, children }: { className: string; children: ReactNode }) {
    const t = useT();
    const [items, setItems] = useState<ClubNotification[] | null>(null);

    const load = async (open: boolean) => {
        if (!open) return;
        try {
            const response = await fetch(route('notifications.latest'), { headers: { Accept: 'application/json' } });
            setItems((await response.json()).notifications);
        } catch {
            setItems([]);
        }
    };

    return (
        <DropdownMenu onOpenChange={load}>
            <DropdownMenuTrigger className={className} aria-label={t('Notifications')}>
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[calc(100dvh-90px)] w-[min(360px,calc(100vw-16px))] overflow-y-auto rounded-xl p-2 shadow-xl"
            >
                <div className="flex items-center justify-between px-2 pt-1 pb-2">
                    <span className="text-2xl font-bold">{t('Notifications')}</span>
                    <PanelMenu>
                        {(close) => (
                            <>
                                <button
                                    type="button"
                                    className={panelMenuItem}
                                    onClick={() => {
                                        close();
                                        router.post(
                                            route('notifications.read'),
                                            {},
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => setItems((list) => list?.map((n) => ({ ...n, read: true })) ?? null),
                                            },
                                        );
                                    }}
                                >
                                    <Check />
                                    {t('Mark all as read')}
                                </button>
                                <Link href={route('notifications.index')} className={panelMenuItem}>
                                    <Settings2 />
                                    {t('Open notifications')}
                                </Link>
                            </>
                        )}
                    </PanelMenu>
                </div>
                {items === null ? (
                    <p className="text-muted-foreground p-6 text-center text-sm">…</p>
                ) : (
                    <NotificationList notifications={items} compact />
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
