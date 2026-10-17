import { TranslatedText } from '@/components/translated-text';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type Post, type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Heart, MessageCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type Lookup = (type: string, id: number) => Record<string, string> | undefined;

export function PostCard({ post, translated }: { post: Post; translated: Lookup }) {
    const t = useT();
    const getInitials = useInitials();
    const { locale } = usePage<SharedData>().props;
    const form = useForm({ body: '' });

    const like = useForm({});
    const submitComment: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('comments.store', post.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const date = post.created_at ? new Date(post.created_at).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' }) : '';

    return (
        <article className="border-sidebar-border/70 dark:border-sidebar-border space-y-3 rounded-xl border p-4">
            <header className="flex items-center gap-3">
                <Avatar className="size-9">
                    <AvatarImage src={post.author?.avatar ?? undefined} alt={post.author?.name} />
                    <AvatarFallback>{getInitials(post.author?.name ?? '?')}</AvatarFallback>
                </Avatar>
                <div className="min-w-0 text-sm">
                    <div className="font-medium">{post.author?.name ?? t('Former member')}</div>
                    <div className="text-muted-foreground truncate text-xs">
                        {date}
                        {post.group && (
                            <>
                                {' · '}
                                <Link className="hover:underline" href={route('groups.show', post.group.slug)}>
                                    {post.group.name}
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <TranslatedText item={post} fetched={translated('post', post.id)} original={{ title: post.title, body: post.body }} />

            <div className="flex items-center gap-4 text-sm">
                <button
                    type="button"
                    className={`inline-flex items-center gap-1 ${post.liked ? 'text-red-500' : 'text-muted-foreground'}`}
                    onClick={() => like.post(route('posts.like', post.id), { preserveScroll: true })}
                >
                    <Heart className={`size-4 ${post.liked ? 'fill-current' : ''}`} /> {post.likes_count}
                </button>
                <span className="text-muted-foreground inline-flex items-center gap-1">
                    <MessageCircle className="size-4" /> {post.comments.length}
                </span>
            </div>

            {post.comments.length > 0 && (
                <ul className="space-y-3 border-s-2 ps-3">
                    {post.comments.map((comment) => (
                        <li key={comment.id} className="text-sm">
                            <div className="text-xs font-medium">{comment.author?.name ?? t('Former member')}</div>
                            <TranslatedText item={comment} fetched={translated('comment', comment.id)} original={{ body: comment.body }} />
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={submitComment} className="flex gap-2">
                <input
                    className="border-input flex-1 rounded-md border bg-transparent px-3 py-1.5 text-sm"
                    placeholder={t('Write a comment…')}
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                />
                <Button size="sm" disabled={form.processing || !form.data.body.trim()}>
                    {t('Send')}
                </Button>
            </form>
        </article>
    );
}
