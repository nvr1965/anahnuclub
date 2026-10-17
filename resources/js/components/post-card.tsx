import { TranslatedText } from '@/components/translated-text';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Post, type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Heart, MessageCircle, SendHorizontal } from 'lucide-react';
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

    const boxed = Boolean(post.title);
    const text = (
        <TranslatedText
            item={post}
            fetched={translated('post', post.id)}
            original={{ title: post.title, body: post.body }}
            clamp
            titleClassName="text-lg leading-snug font-semibold"
        />
    );

    return (
        <article className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 dark:bg-neutral-900 dark:ring-neutral-800">
            <header className="flex items-center gap-3 px-5 pt-5">
                <Avatar className="size-11">
                    <AvatarImage src={post.author?.avatar ?? undefined} alt={post.author?.name} className="object-cover" />
                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(post.author?.name ?? '?')}</AvatarFallback>
                </Avatar>
                <div className="min-w-0 text-sm">
                    <div>
                        <span className="text-brand font-medium">{post.author?.name ?? t('Former member')}</span>
                        {actions[post.type] && <span className="text-muted-foreground"> · {t(actions[post.type])}</span>}
                    </div>
                    <div className="text-muted-foreground text-xs" title={post.created_at ?? ''}>
                        {relativeTime(post.created_at, locale)}
                    </div>
                </div>
            </header>

            <div className="px-5 pt-4">
                {boxed ? (
                    <div className="rounded-xl bg-slate-50 p-4 dark:bg-neutral-800/60">
                        {text}
                        {post.group && (
                            <Link href={route('groups.show', post.group.slug)} className="text-muted-foreground hover:text-brand mt-3 block text-sm">
                                {post.group.name}
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        {text}
                        {post.group && (
                            <Link href={route('groups.show', post.group.slug)} className="text-muted-foreground hover:text-brand mt-2 block text-xs">
                                {post.group.name}
                            </Link>
                        )}
                    </>
                )}
            </div>

            <div className="flex items-center gap-1 px-3 py-2 text-sm">
                <button
                    type="button"
                    disabled={!auth.user}
                    className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 transition hover:bg-slate-100 dark:hover:bg-neutral-800 ${post.liked ? 'text-rose-500' : 'text-muted-foreground'}`}
                    onClick={() => like.post(route('posts.like', post.id), { preserveScroll: true })}
                >
                    <Heart className={`size-4 ${post.liked ? 'fill-current' : ''}`} /> {post.likes_count}
                </button>
                <button
                    type="button"
                    disabled={!auth.user}
                    className="text-muted-foreground inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 transition hover:bg-slate-100 dark:hover:bg-neutral-800"
                    onClick={() => setCommenting(true)}
                >
                    <MessageCircle className="size-4" /> {post.comments.length}
                </button>
            </div>

            {(post.comments.length > 0 || commenting) && (
                <div className="space-y-3 border-t border-slate-100 px-5 py-4 dark:border-neutral-800">
                    {post.comments.map((comment) => (
                        <div key={comment.id} className="flex gap-2.5">
                            <Avatar className="size-8">
                                <AvatarImage src={comment.author?.avatar ?? undefined} alt="" className="object-cover" />
                                <AvatarFallback className="bg-brand-soft text-brand text-xs">{getInitials(comment.author?.name ?? '?')}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1 rounded-2xl bg-slate-100 px-3.5 py-2 text-sm dark:bg-neutral-800">
                                <div className="text-xs font-medium">
                                    {comment.author?.name ?? t('Former member')}
                                    <span className="text-muted-foreground font-normal"> · {relativeTime(comment.created_at, locale)}</span>
                                </div>
                                <TranslatedText item={comment} fetched={translated('comment', comment.id)} original={{ body: comment.body }} />
                            </div>
                        </div>
                    ))}

                    {auth.user && (
                        <form onSubmit={submitComment} className="flex items-center gap-2">
                            <input
                                className="focus:ring-brand/30 flex-1 rounded-full bg-slate-100 px-4 py-2 text-sm outline-none focus:ring-2 dark:bg-neutral-800"
                                placeholder={t('Write a comment…')}
                                value={form.data.body}
                                autoFocus={commenting}
                                onChange={(e) => form.setData('body', e.target.value)}
                            />
                            <button
                                className="bg-brand hover:bg-brand-dark rounded-full p-2 text-white disabled:opacity-40"
                                disabled={form.processing || !form.data.body.trim()}
                                aria-label={t('Send')}
                            >
                                <SendHorizontal className="size-4 rtl:rotate-180" />
                            </button>
                        </form>
                    )}
                </div>
            )}
        </article>
    );
}
