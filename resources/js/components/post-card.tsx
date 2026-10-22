import { AudienceIcon, AudiencePicker, useAudienceLabels } from '@/components/audience-picker';
import { EmbedPostDialog } from '@/components/embed-post-dialog';
import { ExternalEmbed } from '@/components/external-embed';
import { LinkPreview } from '@/components/link-preview';
import { EventCard, PostPhotos, PostVideo } from '@/components/post-media';
import { ReactionButton, ReactionFaces } from '@/components/reactions';
import { ShareMenu } from '@/components/share-menu';
import { TranslatedText } from '@/components/translated-text';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Comment, type Post, type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Code, Ellipsis, Eye, MessageCircle, Pencil, SendHorizontal, Trash2 } from 'lucide-react';
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
    const { locale, auth } = usePage<SharedData>().props;
    const [commenting, setCommenting] = useState(full);
    const [typing, setTyping] = useState(full);
    const form = useForm({ body: '' });
    const reply = useForm({ body: '' });
    const [replyTo, setReplyTo] = useState<{ thread: number; id: number } | null>(null);
    const [expanded, setExpanded] = useState<number[]>([]);
    const [editing, setEditing] = useState(false);
    const edit = useForm({ body: post.editable_text ?? '' });
    const [choosingAudience, setChoosingAudience] = useState(false);
    const [embedding, setEmbedding] = useState(false);
    const audienceLabels = useAudienceLabels();
    const mine = auth.user?.id === post.author?.id;
    const canChangeAudience = mine && !post.group;

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

    // Comments with their replies under them, one level deep as on Facebook.
    const threads = post.comments
        .filter((c) => !c.parent_id || !post.comments.some((p) => p.id === c.parent_id))
        .map((comment) => ({ comment, replies: post.comments.filter((c) => c.parent_id === comment.id) }));

    const startReply = (thread: number, to: Comment) => {
        const name = to.author && to.author.id !== auth.user?.id ? `${to.author.name} ` : '';
        setReplyTo({ thread, id: to.id });
        reply.setData('body', name);
    };

    const submitReply: FormEventHandler = (e) => {
        e.preventDefault();
        if (!replyTo) return;
        reply.transform((data) => ({ ...data, parent_id: replyTo.id }));
        reply.post(route('comments.store', post.id), {
            preserveScroll: true,
            onSuccess: () => {
                setExpanded((all) => [...all, replyTo.thread]);
                setReplyTo(null);
                reply.reset();
            },
        });
    };

    const renderComment = (comment: Comment, thread: number, small = false) => (
        <div key={comment.id} className="flex gap-2">
            <Avatar className={small ? 'size-6' : 'size-8'}>
                <AvatarImage src={comment.author?.avatar ?? undefined} alt="" className="object-cover" />
                <DefaultAvatarFallback />
            </Avatar>
            <div className="min-w-0">
                <div className="rounded-2xl bg-slate-100 px-3 py-2 text-[15px] dark:bg-neutral-800">
                    {comment.author ? (
                        <Link href={route('people.show', comment.author.id)} className="block text-[13px] font-semibold hover:underline">
                            {comment.author.name}
                        </Link>
                    ) : (
                        <div className="text-[13px] font-semibold">{t('Former member')}</div>
                    )}
                    <TranslatedText item={comment} fetched={translated('comment', comment.id)} original={{ body: comment.body }} />
                </div>
                <div className="text-muted-foreground flex gap-3 px-3 pt-0.5 text-xs">
                    <span>{relativeTime(comment.created_at, locale)}</span>
                    {auth.user && (
                        <button type="button" className="font-semibold hover:underline" onClick={() => startReply(thread, comment)}>
                            {t('Reply')}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );

    const commentForm = (isReply: boolean) => {
        if (!auth.user) return null;
        const f = isReply ? reply : form;
        return (
            <form onSubmit={isReply ? submitReply : submitComment} className="flex items-center gap-2 pt-1">
                <Avatar className={isReply ? 'size-6' : 'size-8'}>
                    <AvatarImage src={auth.user.avatar} alt="" className="object-cover" />
                    <DefaultAvatarFallback />
                </Avatar>
                <div className="flex flex-1 items-center rounded-full bg-slate-100 pe-1 dark:bg-neutral-800">
                    <input
                        dir="auto"
                        className="min-w-0 flex-1 bg-transparent px-3 py-2 text-[15px] outline-none"
                        placeholder={isReply ? t('Write a reply…') : t('Write a comment…')}
                        value={f.data.body}
                        autoFocus={isReply || typing}
                        onChange={(e) => f.setData('body', e.target.value)}
                        onKeyDown={(e) => isReply && e.key === 'Escape' && setReplyTo(null)}
                    />
                    <button
                        className="text-brand rounded-full p-1.5 hover:bg-black/5 disabled:opacity-30"
                        disabled={f.processing || !f.data.body.trim()}
                        aria-label={t('Send')}
                    >
                        <SendHorizontal className="size-5 rtl:rotate-180" />
                    </button>
                </div>
            </form>
        );
    };

    return (
        <article className="rounded-lg bg-white shadow-sm dark:bg-neutral-900">
            <header className="flex items-center gap-2.5 px-4 pt-3">
                {post.official ? (
                    <Avatar className="size-10 shrink-0">
                        <AvatarImage src="/images/logo-mark.png" alt="anahnu.club" />
                    </Avatar>
                ) : (
                    <Link href={profile(post.author?.id) ?? '#'} className="shrink-0">
                        <Avatar className="size-10">
                            <AvatarImage src={post.author?.avatar ?? undefined} alt={post.author?.name} className="object-cover" />
                            <DefaultAvatarFallback />
                        </Avatar>
                    </Link>
                )}
                <div className="min-w-0 flex-1 text-[15px] leading-tight">
                    <div>
                        {post.official ? (
                            <span className="font-semibold">anahnu.club</span>
                        ) : post.author ? (
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
                        {!post.group && (
                            <>
                                {' · '}
                                {canChangeAudience ? (
                                    <button
                                        type="button"
                                        className="inline-flex align-[-2px] hover:text-current"
                                        title={audienceLabels[post.audience]?.title}
                                        aria-label={audienceLabels[post.audience]?.title}
                                        onClick={() => setChoosingAudience(true)}
                                    >
                                        <AudienceIcon audience={post.audience} />
                                    </button>
                                ) : (
                                    <span className="inline-flex align-[-2px]" title={audienceLabels[post.audience]?.title}>
                                        <AudienceIcon audience={post.audience} />
                                    </span>
                                )}
                            </>
                        )}
                    </div>
                </div>
                {canChangeAudience && (
                    <AudiencePicker
                        open={choosingAudience}
                        onClose={() => setChoosingAudience(false)}
                        value={{ audience: post.audience, ids: post.audience_ids, makeDefault: false }}
                        showDefault={false}
                        onDone={(choice) =>
                            router.patch(
                                route('posts.audience', post.id),
                                { audience: choice.audience, audience_ids: choice.ids },
                                { preserveScroll: true, onSuccess: () => setChoosingAudience(false) },
                            )
                        }
                    />
                )}
                {post.embeddable && <EmbedPostDialog open={embedding} onClose={() => setEmbedding(false)} postId={post.id} src={post.embed_url} />}
                {(post.can_delete || post.editable_text !== null || canChangeAudience || post.embeddable) && (
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
                            {canChangeAudience && (
                                <DropdownMenuItem onSelect={() => setChoosingAudience(true)}>
                                    <Eye className="size-4" /> {t('Edit audience')}
                                </DropdownMenuItem>
                            )}
                            {/* Only posts anyone may read can be shown on other sites. */}
                            {post.embeddable && (
                                <DropdownMenuItem onSelect={() => setEmbedding(true)}>
                                    <Code className="size-4" /> {t('Embed')}
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
            {post.link && (
                <div className="mt-2">
                    <LinkPreview link={post.link} />
                </div>
            )}
            {post.embed && (
                <div className="mt-2">
                    <ExternalEmbed embed={post.embed} />
                </div>
            )}
            <div className="pb-2" />

            {(post.likes_count > 0 || post.comments.length > 0) && (
                <div className="text-muted-foreground flex items-center justify-between px-4 pb-2 text-[15px]">
                    <span className="inline-flex items-center gap-1.5">
                        {post.likes_count > 0 && (
                            <>
                                <ReactionFaces types={post.reactions} />
                                {post.likes_count}
                            </>
                        )}
                    </span>
                    {post.comments.length > 0 && (
                        <button type="button" className="hover:underline" aria-expanded={commenting} onClick={() => setCommenting((open) => !open)}>
                            {commentsLabel}
                        </button>
                    )}
                </div>
            )}

            <div className={`mx-4 grid ${post.official ? 'grid-cols-2' : 'grid-cols-3'} border-t border-slate-200 py-1 dark:border-neutral-800`}>
                <ReactionButton
                    mine={post.my_reaction}
                    disabled={!auth.user}
                    className={action}
                    onReact={(type) => router.post(route('posts.like', post.id), type ? { type } : {}, { preserveScroll: true })}
                />
                {!post.official && (
                    <button
                        type="button"
                        disabled={!auth.user}
                        className={`${action} text-muted-foreground`}
                        onClick={() => {
                            setCommenting(true);
                            setTyping(true);
                        }}
                    >
                        <MessageCircle className="size-5" /> {t('Comment')}
                    </button>
                )}
                <ShareMenu url={post.url} title={plainTitle} className={`${action} text-muted-foreground outline-none`} />
            </div>

            {/* In the feed comments stay folded behind their count, as on Facebook; a post's own page shows them. */}
            {commenting && !post.official && (
                <div className="space-y-2 border-t border-slate-200 px-4 py-3 dark:border-neutral-800">
                    {threads.map(({ comment, replies }) => {
                        const open = expanded.includes(comment.id) || replies.length <= 2;
                        const shown = open ? replies : replies.slice(-1);
                        return (
                            <div key={comment.id} className="space-y-2">
                                {renderComment(comment, comment.id)}
                                {(replies.length > 0 || replyTo?.thread === comment.id) && (
                                    <div className="ms-10 space-y-2">
                                        {!open && (
                                            <button
                                                type="button"
                                                className="text-muted-foreground text-sm font-semibold hover:underline"
                                                onClick={() => setExpanded((all) => [...all, comment.id])}
                                            >
                                                {t('View :count more replies', { count: replies.length - shown.length })}
                                            </button>
                                        )}
                                        {shown.map((reply) => renderComment(reply, comment.id, true))}
                                        {replyTo?.thread === comment.id && commentForm(true)}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    {commentForm(false)}
                </div>
            )}
        </article>
    );
}
