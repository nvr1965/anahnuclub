import { TranslatedText } from '@/components/translated-text';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Post, type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { MessageCircle, SendHorizontal, ThumbsUp } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Lookup = (type: string, id: number) => Record<string, string> | undefined;

const actions: Record<string, string> = {
    discussion: 'new discussion',
    announcement: 'new announcement',
    photo: 'new photo',
};

export function PostCard({ post, translated }: { post: Post; translated: Lookup }) {
    const t = useT();
    const getInitials = useInitials();
    const { locale, auth } = usePage<SharedData>().props;
    const [commenting, setCommenting] = useState(false);
    const form = useForm({ body: '' });
    const like = useForm({});

    const submitComment: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('comments.store', post.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const commentsLabel = t(':count comments', { count: post.comments.length });

    return (
        <article className="rounded-lg bg-white shadow-sm dark:bg-neutral-900">
            <header className="flex items-center gap-2.5 px-4 pt-3">
                <Avatar className="size-10">
                    <AvatarImage src={post.author?.avatar ?? undefined} alt={post.author?.name} className="object-cover" />
                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(post.author?.name ?? '?')}</AvatarFallback>
                </Avatar>
                <div className="min-w-0 text-[15px] leading-tight">
                    <div>
                        <span className="font-semibold">{post.author?.name ?? t('Former member')}</span>
                        {post.group && (
                            <>
                                <span className="text-muted-foreground"> <span className="inline-block rtl:-scale-x-100">▸</span> </span>
                                <Link href={route('groups.show', post.group.slug)} className="font-semibold hover:underline">
                                    {post.group.name}
                                </Link>
                            </>
                        )}
                    </div>
                    <div className="text-muted-foreground mt-0.5 text-[13px]" title={post.created_at ?? ''}>
                        {relativeTime(post.created_at, locale)}
                        {actions[post.type] && <> · {t(actions[post.type])}</>}
                    </div>
                </div>
            </header>

            <div className="px-4 pt-3 pb-2">
                <TranslatedText
                    item={post}
                    fetched={translated('post', post.id)}
                    original={{ title: post.title, body: post.body }}
                    clamp
                    titleClassName="text-lg leading-snug font-semibold"
                />
            </div>

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

            <div className="mx-4 grid grid-cols-2 border-t border-slate-200 py-1 dark:border-neutral-800">
                <button
                    type="button"
                    disabled={!auth.user}
                    className={`inline-flex items-center justify-center gap-2 rounded-md py-1.5 text-[15px] font-semibold transition hover:bg-slate-100 dark:hover:bg-neutral-800 ${post.liked ? 'text-brand' : 'text-muted-foreground'}`}
                    onClick={() => like.post(route('posts.like', post.id), { preserveScroll: true })}
                >
                    <ThumbsUp className={`size-5 ${post.liked ? 'fill-current' : ''}`} /> {t('Like')}
                </button>
                <button
                    type="button"
                    disabled={!auth.user}
                    className="text-muted-foreground inline-flex items-center justify-center gap-2 rounded-md py-1.5 text-[15px] font-semibold transition hover:bg-slate-100 dark:hover:bg-neutral-800"
                    onClick={() => setCommenting(true)}
                >
                    <MessageCircle className="size-5" /> {t('Comment')}
                </button>
            </div>

            {(post.comments.length > 0 || commenting) && (
                <div className="space-y-2 border-t border-slate-200 px-4 py-3 dark:border-neutral-800">
                    {post.comments.map((comment) => (
                        <div key={comment.id} className="flex gap-2">
                            <Avatar className="size-8">
                                <AvatarImage src={comment.author?.avatar ?? undefined} alt="" className="object-cover" />
                                <AvatarFallback className="bg-brand-soft text-brand text-xs">{getInitials(comment.author?.name ?? '?')}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <div className="rounded-2xl bg-slate-100 px-3 py-2 text-[15px] dark:bg-neutral-800">
                                    <div className="text-[13px] font-semibold">{comment.author?.name ?? t('Former member')}</div>
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
