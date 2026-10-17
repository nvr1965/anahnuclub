import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

interface GroupRow {
    id: number;
    name: string;
    slug: string;
    avatar: string | null;
    members_count: number;
    posts_count: number;
}

export default function GroupsIndex({ groups }: { groups: { data: GroupRow[]; next_page_url: string | null; prev_page_url: string | null } }) {
    const t = useT();

    return (
        <AppLayout breadcrumbs={[{ title: t('Groups'), href: route('groups.index') }]}>
            <Head title={t('Groups')} />
            <div className="mx-auto grid w-full max-w-4xl gap-3 p-4 sm:grid-cols-2">
                {groups.data.map((group) => (
                    <Link
                        key={group.id}
                        href={route('groups.show', group.slug)}
                        className="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent flex items-center gap-3 rounded-xl border p-3"
                    >
                        {group.avatar ? (
                            <img src={group.avatar} alt="" className="size-10 rounded-lg object-cover" />
                        ) : (
                            <div className="bg-muted size-10 rounded-lg" />
                        )}
                        <div className="min-w-0">
                            <div className="truncate font-medium">{group.name}</div>
                            <div className="text-muted-foreground text-xs">
                                {t(':count members', { count: group.members_count })} · {t(':count posts', { count: group.posts_count })}
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
            <nav className="mx-auto flex w-full max-w-4xl justify-between p-4 text-sm">
                {groups.prev_page_url ? <Link href={groups.prev_page_url}>{t('Previous')}</Link> : <span />}
                {groups.next_page_url && <Link href={groups.next_page_url}>{t('Next')}</Link>}
            </nav>
        </AppLayout>
    );
}
