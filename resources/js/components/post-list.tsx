import { PostCard } from '@/components/post-card';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import { useT } from '@/lib/i18n';
import { type Paginated, type Post, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

export function PostList({ posts, full = false }: { posts: Paginated<Post> | { data: Post[]; links?: undefined }; full?: boolean }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const items = useMemo(() => posts.data.flatMap((post) => [post, ...post.comments]), [posts]);
    const translated = useAutoTranslate(items, locale);

    return (
        <div className="space-y-4">
            {posts.data.length === 0 && (
                <p className="text-muted-foreground rounded-2xl bg-white p-6 text-center text-sm dark:bg-neutral-900">{t('No posts yet.')}</p>
            )}
            {posts.data.map((post) => (
                <PostCard key={post.id} post={post} translated={translated} full={full} />
            ))}
            <nav className="text-brand flex justify-between px-1 text-sm font-medium">
                {posts.links?.prev ? (
                    <Link href={posts.links.prev} preserveScroll={false}>
                        {t('Newer')}
                    </Link>
                ) : (
                    <span />
                )}
                {posts.links?.next && <Link href={posts.links.next}>{t('Older')}</Link>}
            </nav>
        </div>
    );
}
