import { GroupForm } from '@/components/group-form';
import { InviteButton } from '@/components/invite-button';
import AppLayout from '@/layouts/app-layout';
import { defaultImages } from '@/lib/default-images';
import { useT } from '@/lib/i18n';
import { type Sidebar } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { MessageSquare, Search, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

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
    q,
    sidebar,
}: {
    groups: { data: GroupRow[]; next_page_url: string | null; prev_page_url: string | null };
    q: string;
    sidebar: Sidebar;
}) {
    const t = useT();
    const [query, setQuery] = useState(q ?? '');
    const first = useRef(true);

    // The list narrows as the name is typed.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get(route('groups.index'), { q: query.trim() || undefined }, { preserveState: true, preserveScroll: true, replace: true });
        }, 350);
        return () => clearTimeout(timer);
    }, [query]);

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
            <form
                role="search"
                onSubmit={(e) => e.preventDefault()}
                className="mb-4 flex items-center gap-2 rounded-full bg-white px-4 py-2 shadow-sm dark:bg-neutral-900"
            >
                <Search className="text-muted-foreground size-4 shrink-0" />
                <input
                    type="search"
                    dir="auto"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={t('Search groups')}
                    aria-label={t('Search groups')}
                    className="min-w-0 flex-1 bg-transparent text-sm outline-none [&::-webkit-search-cancel-button]:hidden"
                />
                {query && (
                    <button
                        type="button"
                        onClick={() => setQuery('')}
                        aria-label={t('Clear')}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </form>
            {groups.data.length === 0 && <p className="text-muted-foreground py-10 text-center">{t('Nothing found.')}</p>}
            <div className="grid gap-3 sm:grid-cols-2">
                {groups.data.map((group) => (
                    <Link
                        key={group.id}
                        href={route('groups.show', group.slug)}
                        className="group flex items-center gap-4 rounded-lg bg-white p-3 shadow-sm transition hover:shadow-md dark:bg-neutral-900"
                    >
                        <img src={group.avatar || defaultImages.group} loading="lazy" alt="" className="size-14 shrink-0 rounded-xl object-cover" />
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
