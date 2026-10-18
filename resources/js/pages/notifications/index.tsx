import { type ClubNotification, NotificationList } from '@/components/notification-list';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';

export default function Notifications({ notifications }: { notifications: ClubNotification[] }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('Notifications')} />
            <section className="rounded-lg bg-white p-3 shadow-sm dark:bg-neutral-900">
                <div className="flex items-center justify-between px-2 pb-2">
                    <h1 className="text-2xl font-bold">{t('Notifications')}</h1>
                    {notifications.some((n) => !n.read) && (
                        <button
                            type="button"
                            className="text-brand text-sm font-semibold hover:underline"
                            onClick={() => router.post(route('notifications.read'), {}, { preserveScroll: true })}
                        >
                            {t('Mark all as read')}
                        </button>
                    )}
                </div>
                <NotificationList notifications={notifications} />
            </section>
        </AppLayout>
    );
}
