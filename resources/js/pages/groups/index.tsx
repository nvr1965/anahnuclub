import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { MessageSquare, Users } from 'lucide-react';

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
        <AppLayout>
            <Head title={t('Groups')} />
            <h1 className="mb-4 text-2xl font-semibold">{t('Groups')}</h1>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {groups.data.map((group) => (
                    <Link
                        key={group.id}
                        href={route('groups.show', group.slug)}
                        className="group flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-neutral-900 dark:ring-neutral-800"
                    >
                        {group.avatar ? (
                            <img src={group.avatar} alt="" className="size-14 rounded-xl object-cover" />
                        ) : (
                            <div className="from-brand to-brand-dark flex size-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-xl font-semibold text-white">
                                {group.name.trim().charAt(0).toUpperCase()}
                            </div>
                        )}
                        <div className="min-w-0">
                            <div dir="auto" className="group-hover:text-brand truncate font-medium">
                                {group.name}
                            </div>
                            <div className="text-muted-foreground mt-1 flex gap-3 text-xs">
                                <span className="inline-flex items-center gap-1">
                                    <Users className="size-3.5" /> {group.members_count}
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <MessageSquare className="size-3.5" /> {group.posts_count}
                                </span>
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
            <nav className="text-brand mt-6 flex justify-between text-sm font-medium">
                {groups.prev_page_url ? <Link href={groups.prev_page_url}>{t('Previous')}</Link> : <span />}
                {groups.next_page_url && <Link href={groups.next_page_url}>{t('Next')}</Link>}
            </nav>
        </AppLayout>
    );
}
