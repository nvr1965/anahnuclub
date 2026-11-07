import { PostList } from '@/components/post-list';
import AppLayout from '@/layouts/app-layout';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type Paginated, type Post, type SharedData, type Sidebar } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Hash } from 'lucide-react';

/** Every post with a #hashtag the reader may see, newest first, and the hashtags popular lately. */
export default function HashtagShow({ tag, posts, popular, sidebar }: { tag: string; posts: Paginated<Post>; popular: string[]; sidebar: Sidebar }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const others = popular.filter((p) => p !== tag);
    const content = (
        <div className="space-y-4">
            <Head title={`#${tag}`} />
            <div className="rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900">
                <div className="flex items-center gap-3">
                    <span className="bg-brand-soft text-brand flex size-12 shrink-0 items-center justify-center rounded-full">
                        <Hash className="size-6" />
                    </span>
                    <div className="min-w-0">
                        <h1 dir="auto" className="truncate text-xl font-bold">
                            #{tag}
                        </h1>
                        <p className="text-muted-foreground text-sm">{t('Posts with this hashtag')}</p>
                    </div>
                </div>
                {others.length > 0 && (
                    <div className="mt-3 border-t border-slate-200 pt-3 dark:border-neutral-800">
                        <p className="text-muted-foreground mb-2 text-xs font-semibold uppercase">{t('Popular hashtags')}</p>
                        <div className="flex flex-wrap gap-1.5">
                            {others.map((p) => (
                                <Link
                                    key={p}
                                    href={route('hashtags.show', p)}
                                    dir="auto"
                                    className="text-brand rounded-full bg-slate-100 px-3 py-1 text-sm font-medium hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                                >
                                    #{p}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
            <PostList posts={posts} />
        </div>
    );

    return auth.user ? (
        <AppLayout sidebar={sidebar}>{content}</AppLayout>
    ) : (
        <ClubLayout>
            <div className="mx-auto w-full max-w-[680px]">{content}</div>
        </ClubLayout>
    );
}
