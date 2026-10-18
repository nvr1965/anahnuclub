import { PostList } from '@/components/post-list';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { type Post, type Sidebar } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

interface Results {
    people: { id: number; name: string; avatar: string | null }[];
    groups: { name: string; slug: string; members_count: number }[];
    posts: { data: Post[] } | Post[];
}

export default function SearchPage({ q, results, sidebar }: { q: string; results: Results; sidebar: Sidebar }) {
    const t = useT();
    const getInitials = useInitials();
    const posts = Array.isArray(results.posts) ? { data: results.posts } : results.posts;
    const empty = results.people.length === 0 && results.groups.length === 0 && posts.data.length === 0;
    const [query, setQuery] = useState(q);
    const card = 'rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900';

    return (
        <AppLayout sidebar={sidebar}>
            <Head title={t('Search')} />
            <div className="space-y-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (query.trim()) router.get(route('search'), { q: query.trim() });
                    }}
                    className="flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm sm:hidden dark:bg-neutral-900"
                >
                    <Search className="text-muted-foreground size-4 shrink-0" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Search anahnu.club')}
                        autoFocus={!q}
                        className="w-full bg-transparent text-sm outline-none"
                    />
                </form>
                {q && <h1 className="text-xl font-bold">{t('Results for “:q”', { q })}</h1>}
                {q && empty && <p className={`${card} text-muted-foreground text-center`}>{t('Nothing found.')}</p>}

                {results.people.length > 0 && (
                    <section className={card}>
                        <h2 className="mb-3 font-semibold">{t('People')}</h2>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {results.people.map((person) => (
                                <Link key={person.id} href={route('people.show', person.id)} className="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-neutral-800">
                                    <Avatar className="size-10">
                                        <AvatarImage src={person.avatar ?? undefined} alt="" className="object-cover" />
                                        <AvatarFallback className="bg-brand-soft text-brand">{getInitials(person.name)}</AvatarFallback>
                                    </Avatar>
                                    <span dir="auto" className="truncate text-[15px] font-medium">
                                        {person.name}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {results.groups.length > 0 && (
                    <section className={card}>
                        <h2 className="mb-3 font-semibold">{t('Groups')}</h2>
                        <ul className="space-y-1">
                            {results.groups.map((group) => (
                                <li key={group.slug}>
                                    <Link href={route('groups.show', group.slug)} className="flex justify-between rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800">
                                        <span dir="auto" className="font-medium">
                                            {group.name}
                                        </span>
                                        <span className="text-muted-foreground shrink-0 text-sm whitespace-nowrap">{t(':count members', { count: group.members_count })}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {posts.data.length > 0 && (
                    <>
                        <h2 className="pt-2 font-semibold">{t('Posts')}</h2>
                        <PostList posts={posts} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
