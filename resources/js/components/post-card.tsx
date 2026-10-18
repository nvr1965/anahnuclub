import { EventCard, PostPhotos, PostVideo } from '@/components/post-media';
import { ShareMenu } from '@/components/share-menu';
import { TranslatedText } from '@/components/translated-text';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Post, type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Ellipsis, MessageCircle, Pencil, SendHorizontal, ThumbsUp, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Lookup = (type: string, id: number) => Record<string, string> | undefined;

const actions: Record<string, string> = {
    discussion: 'new discussion',
    announcement: 'new announcement',
    photo: 'new photo',
    video: 'new video',
    event: 'new event',
};

export function PostCard({ post, translated, full = false }: { post: Post; translated: Lookup; full?: boolean }) {
    const t = useT();
    const getInitials = useInitials();
    const { locale, auth } = usePage<SharedData>().props;
    const [commenting, setCommenting] = useState(full);
    const form = useForm({ body: '' });
    const like = useForm({});
    const [editing, setEditing] = useState(false);
    const edit = useForm({ body: post.editable_text ?? '' });

    const saveEdit: FormEventHandler = (e) => {
        e.preventDefault();
        edit.patch(route('posts.update', post.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const remove = () => {
        if (window.confirm(t('Delete this post?'))) router.delete(route('posts.destroy', post.id), { preserveScroll: true });
    };

    const hasText = Boolean(post.title) || post.body.replace(/<br\s*\/?>|&nbsp;|\s/g, '') !== '';
    const profile = (id?: number) => (id ? route('people.show', id) : undefined);
    const plainTitle =
        post.title ??
        post.body
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 120);
    const action =
        'inline-flex items-center justify-center gap-2 rounded-md py-1.5 text-[15px] font-semibold transition hover:bg-slate-100 max-sm:flex-col max-sm:gap-0.5 max-sm:text-xs dark:hover:bg-neutral-800';

    const submitComment: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('comments.store', post.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const commentsLabel = t(':count comments', { count: post.comments.length });

    return (
        <article className="rounded-lg bg-white shadow-sm dark:bg-neutral-900">
            <header className="flex items-center gap-2.5 px-4 pt-3">
                <Link href={profile(post.author?.id) ?? '#'} className="shrink-0">
                    <Avatar className="size-10">
                        <AvatarImage src={post.author?.avatar ?? undefined} alt={post.author?.name} className="object-cover" />
                        <AvatarFallback className="bg-brand-soft text-brand">{getInitials(post.author?.name ?? '?')}</AvatarFallback>
                    </Avatar>
                </Link>
                <div className="min-w-0 flex-1 text-[15px] leading-tight">
                    <div>
                        {post.author ? (
                            <Link href={route('people.show', post.author.id)} className="font-semibold hover:underline">
                                {post.author.name}
                            </Link>
                        ) : (
                            <span className="font-semibold">{t('Former member')}</span>
                        )}
                        {post.group && (
                            <>
                                <span className="text-muted-foreground">
                                    {' '}
                                    <span className="inline-block rtl:-scale-x-100">▸</span>{' '}
                                </span>
                                <Link href={route('groups.show', post.group.slug)} className="font-semibold hover:underline">
                                    {post.group.name}
                                </Link>
                            </>
                        )}
                    </div>
                    <div className="text-muted-foreground mt-0.5 text-[13px]" title={post.created_at ?? ''}>
                        <Link href={post.url} className="hover:underline">
                            {relativeTime(post.created_at, locale)}
                        </Link>
                        {actions[post.type] && <> · {t(actions[post.type])}</>}
                    </div>
                </div>
                {(post.can_delete || post.editable_text !== null) && (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            className="text-muted-foreground -me-2 self-start rounded-full p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                            aria-label={t('Actions')}
                        >
                            <Ellipsis className="size-5" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {post.editable_text !== null && (
                                <DropdownMenuItem onSelect={() => setEditing(true)}>
                                    <Pencil className="size-4" /> {t('Edit')}
                                </DropdownMenuItem>
                            )}
                            {post.can_delete && (
                                <DropdownMenuItem onSelect={remove} className="text-red-600">
                                    <Trash2 className="size-4" /> {t('Delete')}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </header>

            {(editing || hasText) && (
                <div className="px-4 pt-3 pb-2">
                    {editing ? (
                        <form onSubmit={saveEdit} className="space-y-2">
                            <textarea
                                dir="auto"
                                autoFocus
                                rows={5}
                                className="w-full resize-y rounded-xl bg-slate-100 px-4 py-2.5 text-[15px] outline-none dark:bg-neutral-800"
                                value={edit.data.body}
                                onChange={(e) => edit.setData('body', e.target.value)}
                            />
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setEditing(false)}
                                    className="text-muted-foreground rounded-md px-4 py-1.5 text-sm font-semibold hover:bg-slate-100"
                                >
                                    {t('Cancel')}
                                </button>
                                <button
                                    disabled={edit.processing || (post.type === 'status' && !edit.data.body.trim())}
                                    className="bg-brand hover:bg-brand-dark rounded-md px-5 py-1.5 text-sm font-semibold text-white disabled:opacity-40"
                                >
                                    {t('Save')}
                                </button>
                            </div>
                        </form>
                    ) : (
                        <TranslatedText
                            item={post}
                            fetched={translated('post', post.id)}
                            original={{ title: post.title, body: post.body }}
                            clamp={!full}
                            titleClassName="text-lg leading-snug font-semibold"
                        />
                    )}
                </div>
            )}
            {post.photos.length > 0 && (
                <div className="mt-2">
                    <PostPhotos photos={post.photos} />
                </div>
            )}
            {post.video && (
                <div className="mt-2">
                    <PostVideo video={post.video} />
                </div>
            )}
            {post.event && (
                <div className="mt-2">
                    <EventCard event={post.event} />
                </div>
            )}
            <div className="pb-2" />

            {(post.likes_count > 0 || post.comments.length > 0) && (
                <div className="text-muted-foreground flex items-center justify-between px-4 pb-2 text-[15px]">
                    <span className="inline-flex items-center gap-1.5">
                        {post.likes_count > 0 && (
                            <>
                                <span className="bg-brand inline-flex size-[18px] items-center justify-center rounded-full">
                                    <ThumbsUp className="size-2.5 fill-white text-white" />
                                </span>
                                {post.likes_count}
                            </>
                        )}
                    </span>
                    {post.comments.length > 0 && (
                        <button type="button" className="hover:underline" onClick={() => setCommenting(true)}>
                            {commentsLabel}
                        </button>
                    )}
                </div>
            )}

            <div className="mx-4 grid grid-cols-3 border-t border-slate-200 py-1 dark:border-neutral-800">
                <button
                    type="button"
                    disabled={!auth.user}
                    className={`${action} ${post.liked ? 'text-brand' : 'text-muted-foreground'}`}
                    onClick={() => like.post(route('posts.like', post.id), { preserveScroll: true })}
                >
                    <ThumbsUp className={`size-5 ${post.liked ? 'fill-current' : ''}`} /> {t('Like')}
                </button>
                <button type="button" disabled={!auth.user} className={`${action} text-muted-foreground`} onClick={() => setCommenting(true)}>
                    <MessageCircle className="size-5" /> {t('Comment')}
                </button>
                <ShareMenu url={post.url} title={plainTitle} className={`${action} text-muted-foreground outline-none`} />
            </div>

            {(post.comments.length > 0 || commenting) && (
                <div className="space-y-2 border-t border-slate-200 px-4 py-3 dark:border-neutral-800">
                    {post.comments.map((comment) => (
                        <div key={comment.id} className="flex gap-2">
                            <Avatar className="size-8">
                                <AvatarImage src={comment.author?.avatar ?? undefined} alt="" className="object-cover" />
                                <AvatarFallback className="bg-brand-soft text-brand text-xs">
                                    {getInitials(comment.author?.name ?? '?')}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <div className="rounded-2xl bg-slate-100 px-3 py-2 text-[15px] dark:bg-neutral-800">
                                    {comment.author ? (
                                        <Link
                                            href={route('people.show', comment.author.id)}
                                            className="block text-[13px] font-semibold hover:underline"
                                        >
                                            {comment.author.name}
                                        </Link>
                                    ) : (
                                        <div className="text-[13px] font-semibold">{t('Former member')}</div>
                                    )}
                                    <TranslatedText item={comment} fetched={translated('comment', comment.id)} original={{ body: comment.body }} />
                                </div>
                                <div className="text-muted-foreground px-3 pt-0.5 text-xs">{relativeTime(comment.created_at, locale)}</div>
                            </div>
                        </div>
                    ))}

                    {auth.user && (
                        <form onSubmit={submitComment} className="flex items-center gap-2 pt-1">
                            <Avatar className="size-8">
                                <AvatarImage src={auth.user.avatar} alt="" className="object-cover" />
                                <AvatarFallback className="bg-brand-soft text-brand text-xs">{getInitials(auth.user.name)}</AvatarFallback>
                            </Avatar>
                            <div className="flex flex-1 items-center rounded-full bg-slate-100 pe-1 dark:bg-neutral-800">
                                <input
                                    dir="auto"
                                    className="flex-1 bg-transparent px-3 py-2 text-[15px] outline-none"
                                    placeholder={t('Write a comment…')}
                                    value={form.data.body}
                                    autoFocus={commenting}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                />
                                <button
                                    className="text-brand rounded-full p-1.5 hover:bg-black/5 disabled:opacity-30"
                                    disabled={form.processing || !form.data.body.trim()}
                                    aria-label={t('Send')}
                                >
                                    <SendHorizontal className="size-5 rtl:rotate-180" />
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            )}
        </article>
    );
}
