import { PostList } from '@/components/post-list';
import { EventCard } from '@/components/post-media';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { VideoGrid } from '@/components/video-grid';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type EventItem, type Person, type Post, type Sidebar, type VideoItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Lock, Search } from 'lucide-react';
import { type ReactNode, useState } from 'react';

type Type = 'people' | 'posts' | 'groups' | 'videos' | 'events' | 'pages';

interface Results {
    people: Person[];
    posts: { data: Post[] } | Post[];
    groups: { name: string; slug: string; members_count: number; is_private: boolean }[];
    videos: VideoItem[];
    events: EventItem[];
    pages: { slug: string; title: string }[];
}

interface Props {
    q: string;
    type: Type | 'all';
    counts: Record<Type, number>;
    results: Results;
    more: string | null;
    sidebar: Sidebar;
}

const card = 'rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900';

export default function SearchPage({ q, type, counts, results: raw, more, sidebar }: Props) {
    const posts = Array.isArray(raw.posts) ? raw.posts : raw.posts.data;
    const results = { ...raw, posts };
    const t = useT();
    const [query, setQuery] = useState(q);
    const total = Object.values(counts).reduce((a, b) => a + b, 0);
    const labels: Record<Type, string> = {
        people: t('People'),
        posts: t('Posts'),
        groups: t('Groups'),
        videos: t('Videos'),
        events: t('Events'),
        pages: t('Pages'),
    };
    const go = (next: Type | 'all') => router.get(route('search'), { q, ...(next === 'all' ? {} : { type: next }) });
    // On the "All" tab each kind has a heading with its count and a link to its own tab when there is more of it.
    const section = (key: Type, body: ReactNode, boxed = true) =>
        results[key].length > 0 && (
            <section className={boxed ? card : ''}>
                {type === 'all' && (
                    <div className="mb-3 flex items-baseline justify-between gap-2">
                        <h2 className="font-semibold">
                            {labels[key]} <span className="text-muted-foreground font-normal">{counts[key]}</span>
                        </h2>
                        {counts[key] > results[key].length && (
                            <button type="button" onClick={() => go(key)} className="text-brand text-sm font-semibold hover:underline">
                                {t('Show all')}
                            </button>
                        )}
                    </div>
                )}
                {body}
            </section>
        );

    return (
        <AppLayout sidebar={sidebar}>
            <Head title={q ? `${q} · ${t('Search')}` : t('Search')} />
            <div className="space-y-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (query.trim()) router.get(route('search'), { q: query.trim(), ...(type === 'all' ? {} : { type }) });
                    }}
                    className="flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm sm:hidden dark:bg-neutral-900"
                >
                    <Search className="text-muted-foreground size-4 shrink-0" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Search anahnu.club')}
                        autoFocus={!q}
                        className="w-full bg-transparent text-[15px] outline-none"
                    />
                </form>

                {q.length > 1 && (
                    <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                        {(['all', ...Object.keys(labels)] as (Type | 'all')[]).map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => go(key)}
                                disabled={key !== 'all' && counts[key] === 0}
                                className={`shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold whitespace-nowrap disabled:opacity-40 ${
                                    type === key
                                        ? 'bg-brand text-white'
                                        : 'bg-white shadow-sm hover:bg-slate-100 dark:bg-neutral-900 dark:hover:bg-neutral-800'
                                }`}
                            >
                                {key === 'all' ? t('All') : labels[key]} <span className="opacity-70">{key === 'all' ? total : counts[key]}</span>
                            </button>
                        ))}
                    </div>
                )}

                {q.length === 1 && <p className={`${card} text-muted-foreground text-center`}>{t('Type at least two letters.')}</p>}
                {q.length > 1 && total === 0 && <p className={`${card} text-muted-foreground text-center`}>{t('Nothing found.')}</p>}

                {section(
                    'people',
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {results.people.map((person) => (
                            <Link
                                key={person.id}
                                href={route('people.show', person.id)}
                                className="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-neutral-800"
                            >
                                <Avatar className="size-10">
                                    <AvatarImage src={person.avatar ?? undefined} alt="" className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                                <span dir="auto" className="truncate text-[15px] font-medium">
                                    {person.name}
                                </span>
                            </Link>
                        ))}
                    </div>,
                )}

                {section(
                    'groups',
                    <ul className="space-y-1">
                        {results.groups.map((group) => (
                            <li key={group.slug}>
                                <Link
                                    href={route('groups.show', group.slug)}
                                    className="flex justify-between gap-2 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                >
                                    <span dir="auto" className="flex items-center gap-1.5 font-medium">
                                        {group.is_private && <Lock className="text-muted-foreground size-3.5" />}
                                        {group.name}
                                    </span>
                                    <span className="text-muted-foreground shrink-0 text-sm whitespace-nowrap">
                                        {t(':count members', { count: group.members_count })}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>,
                )}

                {section('videos', <VideoGrid videos={results.videos} />)}

                {section(
                    'events',
                    <div className="grid gap-4 sm:grid-cols-2">
                        {results.events.map((event) => (
                            <div key={event.id} className="overflow-hidden rounded-lg shadow-sm">
                                <EventCard event={event} />
                            </div>
                        ))}
                    </div>,
                )}

                {section(
                    'pages',
                    <ul className="space-y-1">
                        {results.pages.map((page) => (
                            <li key={page.slug}>
                                <Link
                                    href={route('pages.show', page.slug)}
                                    className="flex items-center gap-2 rounded-lg p-2 font-medium hover:bg-slate-100 dark:hover:bg-neutral-800"
                                >
                                    <FileText className="text-muted-foreground size-4 shrink-0" />
                                    <span dir="auto">{page.title}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>,
                )}

                {section('posts', <PostList posts={{ data: posts }} />, false)}

                {more && (
                    <div className="text-center">
                        <Link href={more} className="text-brand text-[15px] font-semibold hover:underline">
                            {t('More')}
                        </Link>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
