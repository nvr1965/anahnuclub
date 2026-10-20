import { card } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

interface Stats {
    users: number;
    blocked: number;
    admins: number;
    groups: number;
    posts: number;
    posts_week: number;
    comments: number;
    translations: number;
}

export default function AdminIndex({ stats, translation_driver }: { stats: Stats; translation_driver: string }) {
    const t = useT();
    const tiles = [
        { label: t('Users'), value: stats.users, note: t(':count blocked', { count: stats.blocked }), href: route('admin.users.index') },
        { label: t('Administrators'), value: stats.admins, href: route('admin.users.index', { filter: 'admins' }) },
        { label: t('Posts'), value: stats.posts, note: t(':count this week', { count: stats.posts_week }), href: route('admin.posts.index') },
        { label: t('Comments'), value: stats.comments },
        { label: t('Groups'), value: stats.groups, href: route('admin.groups.index') },
        {
            label: t('Stored translations'),
            value: stats.translations,
            note: translation_driver === 'null' ? t('Translation is off') : translation_driver,
        },
    ];

    return (
        <AdminLayout title={t('Overview')}>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {tiles.map((tile) => {
                    const body = (
                        <>
                            <div className="text-muted-foreground text-sm">{tile.label}</div>
                            <div className="mt-1 text-3xl font-semibold tabular-nums">{tile.value.toLocaleString()}</div>
                            {tile.note && <div className="text-muted-foreground mt-1 text-xs">{tile.note}</div>}
                        </>
                    );
                    return tile.href ? (
                        <Link key={tile.label} href={tile.href} className={`${card} p-4 transition hover:shadow-md`}>
                            {body}
                        </Link>
                    ) : (
                        <div key={tile.label} className={`${card} p-4`}>
                            {body}
                        </div>
                    );
                })}
            </div>
        </AdminLayout>
    );
}
