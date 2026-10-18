import { type ClubNotification, NotificationList } from '@/components/notification-list';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { type ReactNode, useState } from 'react';

/** The bell in the top bar: opening it loads the latest notices. */
export function NotificationBell({ badge, className }: { badge: ReactNode; className: string }) {
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
                <Bell className="size-5" />
                {badge}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="max-h-[80vh] w-[min(380px,calc(100vw-16px))] overflow-y-auto p-2">
                <div className="flex items-center justify-between px-2 pb-1">
                    <span className="text-xl font-bold">{t('Notifications')}</span>
                    <button
                        type="button"
                        className="text-brand text-sm font-semibold hover:underline"
                        onClick={() =>
                            router.post(
                                route('notifications.read'),
                                {},
                                { preserveScroll: true, onSuccess: () => setItems((list) => list?.map((n) => ({ ...n, read: true })) ?? null) },
                            )
                        }
                    >
                        {t('Mark all as read')}
                    </button>
                </div>
                {items === null ? <p className="text-muted-foreground p-6 text-center text-sm">…</p> : <NotificationList notifications={items} />}
                <Link
                    href={route('notifications.index')}
                    className="text-brand block rounded-md p-2 text-center text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                >
                    {t('See all')}
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
