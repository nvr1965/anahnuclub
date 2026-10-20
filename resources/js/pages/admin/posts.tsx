import { ActionButton, AdminSearch, card, type Page, Pager, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    title: string | null;
    excerpt: string;
    type: string;
    author: { id: number; name: string } | null;
    group: { name: string; slug: string } | null;
    comments_count: number;
    created_at: string | null;
}

export default function AdminPosts({ posts, q, user }: { posts: Page<Row>; q: string; user: number | null }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [selected, setSelected] = useState<number[]>([]);
    const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const remove = (ids: number[]) => {
        if (!confirm(t('Delete :count posts? This cannot be undone.', { count: ids.length }))) return;
        send('delete', route('admin.posts.destroy'), { ids });
        setSelected([]);
    };

    return (
        <AdminLayout title={t('Posts')} actions={<AdminSearch value={q} routeName="admin.posts.index" params={{ user: user ?? undefined }} />}>
            {(user || selected.length > 0) && (
                <div className="mb-3 flex flex-wrap items-center gap-3 text-sm">
                    {user && posts.data[0]?.author && (
                        <span className="rounded-full bg-white px-3 py-1 dark:bg-neutral-900">
                            {t('Author')}: <b dir="auto">{posts.data[0].author.name}</b>{' '}
                            <Link href={route('admin.posts.index', { q: q || undefined })} className="text-brand ms-1">
                                ✕
                            </Link>
                        </span>
                    )}
                    {selected.length > 0 && (
                        <button type="button" onClick={() => remove(selected)} className="rounded-full bg-red-600 px-3 py-1 font-medium text-white">
                            {t('Delete selected (:count)', { count: selected.length })}
                        </button>
                    )}
                </div>
            )}
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {posts.data.length === 0 && <p className="text-muted-foreground p-6 text-center">{t('Nothing found.')}</p>}
                {posts.data.map((post) => (
                    <div key={post.id} className="flex gap-3 p-3">
                        <input
                            type="checkbox"
                            className="accent-brand mt-1 size-4"
                            checked={selected.includes(post.id)}
                            onChange={() => toggle(post.id)}
                        />
                        <div className="min-w-0 flex-1">
                            <div className="text-muted-foreground text-xs">
                                {post.author ? (
                                    <Link
                                        href={route('admin.posts.index', { user: post.author.id })}
                                        className="text-foreground font-medium hover:underline"
                                        dir="auto"
                                    >
                                        {post.author.name}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                                {post.group && (
                                    <>
                                        {' '}
                                        ▸{' '}
                                        <Link href={route('groups.show', post.group.slug)} className="hover:underline" dir="auto">
                                            {post.group.name}
                                        </Link>
                                    </>
                                )}
                                {post.created_at && <> · {relativeTime(post.created_at, locale)}</>} ·{' '}
                                {t(':count comments', { count: post.comments_count })}
                            </div>
                            {post.title && (
                                <div dir="auto" className="mt-0.5 font-medium">
                                    {post.title}
                                </div>
                            )}
                            <div dir="auto" className="text-muted-foreground mt-0.5 line-clamp-2 text-sm">
                                {post.excerpt}
                            </div>
                        </div>
                        <ActionButton danger onClick={() => remove([post.id])}>
                            {t('Delete')}
                        </ActionButton>
                    </div>
                ))}
            </div>
            <Pager page={posts} />
        </AdminLayout>
    );
}
