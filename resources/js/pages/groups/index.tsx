import { GroupForm } from '@/components/group-form';
import { InviteButton } from '@/components/invite-button';
import AppLayout from '@/layouts/app-layout';
import { defaultImages } from '@/lib/default-images';
import { useT } from '@/lib/i18n';
import { type Sidebar } from '@/types';
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

export default function GroupsIndex({
    groups,
    sidebar,
}: {
    groups: { data: GroupRow[]; next_page_url: string | null; prev_page_url: string | null };
    sidebar: Sidebar;
}) {
    const t = useT();

    return (
        <AppLayout sidebar={sidebar}>
            <Head title={t('Groups')} />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">{t('Groups')}</h1>
                <div className="flex flex-wrap gap-2">
                    <GroupForm />
                    <InviteButton />
                </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                {groups.data.map((group) => (
                    <Link
                        key={group.id}
                        href={route('groups.show', group.slug)}
                        className="group flex items-center gap-4 rounded-lg bg-white p-3 shadow-sm transition hover:shadow-md dark:bg-neutral-900"
                    >
                        <img src={group.avatar || defaultImages.group} alt="" className="size-14 shrink-0 rounded-xl object-cover" />
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
