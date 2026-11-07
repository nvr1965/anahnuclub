import { ExternalEmbed } from '@/components/external-embed';
import { LinkPreview } from '@/components/link-preview';
import { EventCard, PostPhotos, PostVideo } from '@/components/post-media';
import { TranslatedText } from '@/components/translated-text';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData, type SharedPost } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';

type Lookup = (type: string, id: number) => Record<string, string> | undefined;

/** The post inside a share, framed as on Facebook: its author and time, its words, pictures, video or link. */
export function SharedPostView({ post, translated }: { post: SharedPost | null; translated: Lookup }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;

    if (!post) {
        return (
            <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-800/40">
                <Lock className="text-muted-foreground mt-0.5 size-5 shrink-0" />
                <div>
                    <p className="font-semibold">{t("This content isn't available right now")}</p>
                    <p className="text-muted-foreground text-sm">{t('The post was deleted or its author changed who can see it.')}</p>
                </div>
            </div>
        );
    }

    const hasText = Boolean(post.title) || post.body.replace(/<br\s*\/?>|&nbsp;|\s/g, '') !== '';

    return (
        <div className="overflow-hidden rounded-lg border border-slate-200 dark:border-neutral-800">
            {post.photos.length > 0 && <PostPhotos photos={post.photos} />}
            {post.video && <PostVideo video={post.video} postId={post.id} />}
            {post.event && <EventCard event={post.event} />}
            <div className="flex items-center gap-2.5 px-3 pt-3">
                {post.official ? (
                    <Avatar className="size-9 shrink-0">
                        <AvatarImage src="/images/logo-mark.png" alt="anahnu.club" />
                    </Avatar>
                ) : (
                    <Link href={post.author ? route('people.show', post.author.id) : '#'} className="shrink-0">
                        <Avatar className="size-9">
                            <AvatarImage src={post.author?.avatar ?? undefined} alt={post.author?.name} className="object-cover" />
                            <DefaultAvatarFallback />
                        </Avatar>
                    </Link>
                )}
                <div className="min-w-0 flex-1 text-[0.9375rem] leading-tight">
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
                    <Link
                        href={post.url}
                        className="text-muted-foreground mt-0.5 block text-[0.8125rem] hover:underline"
                        title={post.created_at ?? ''}
                    >
                        {relativeTime(post.created_at, locale)}
                    </Link>
                </div>
            </div>
            {hasText && (
                <div className="px-3 pt-2">
                    <TranslatedText
                        item={post}
                        fetched={translated('post', post.id)}
                        original={{ title: post.title, body: post.body }}
                        clamp
                        titleClassName="text-lg leading-snug font-semibold"
                    />
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
            <div className="pb-3" />
        </div>
    );
}
