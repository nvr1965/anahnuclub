import { REACTIONS } from '@/components/reactions';
import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { shortAgo } from '@/lib/relative-time';
import { type Person, type Reaction, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarCheck, Crown, MessageCircle, Shield, ShieldCheck, Star, ThumbsUp, UserCheck, UserPlus } from 'lucide-react';
import { Fragment, type ReactNode, useState } from 'react';

export interface ClubNotification {
    id: string;
    kind: string;
    actor: Person | null;
    /** The second person in "Anna and Boris", when several did the same thing. */
    also?: Person | null;
    /** How many more did it besides the two named. */
    others?: number;
    /** The face left on a like. */
    reaction?: Reaction | null;
    excerpt: string | null;
    read: boolean;
    created_at: string | null;
}

/** The sentence for one person (`one`) and for several (`many`), and the badge on the picture. */
const kinds: Record<string, { one: string; many?: string; reacted?: [string, string]; icon: ReactNode; color: string }> = {
    post_liked: {
        one: ':name liked your post',
        reacted: [':name reacted to your post', ':names reacted to your post'],
        icon: <ThumbsUp className="fill-current" />,
        color: 'bg-brand',
    },
    photo_liked: {
        one: ':name liked your photo',
        reacted: [':name reacted to your photo', ':names reacted to your photo'],
        icon: <ThumbsUp className="fill-current" />,
        color: 'bg-brand',
    },
    video_liked: {
        one: ':name liked your video',
        reacted: [':name reacted to your video', ':names reacted to your video'],
        icon: <ThumbsUp className="fill-current" />,
        color: 'bg-brand',
    },
    post_commented: {
        one: ':name commented on your post',
        many: ':names commented on your post',
        icon: <MessageCircle className="fill-current" />,
        color: 'bg-green-500',
    },
    comment_replied: {
        one: ':name replied to your comment',
        many: ':names replied to your comment',
        icon: <MessageCircle className="fill-current" />,
        color: 'bg-green-500',
    },
    comment_also: {
        one: ':name also commented on a post you commented on',
        many: ':names also commented on a post you commented on',
        icon: <MessageCircle className="fill-current" />,
        color: 'bg-green-500',
    },
    friend_request: { one: ':name sent you a friend request', many: ':names sent you friend requests', icon: <UserPlus />, color: 'bg-sky-500' },
    friend_accepted: { one: ':name accepted your friend request', icon: <UserCheck />, color: 'bg-sky-500' },
    event_going: { one: ':name is going to your event', many: ':names are going to your event', icon: <CalendarCheck />, color: 'bg-red-500' },
    event_interested: {
        one: ':name is interested in your event',
        many: ':names are interested in your event',
        icon: <Star />,
        color: 'bg-amber-500',
    },
    group_offer_admin: { one: ':name invites you to be an admin of a group', icon: <ShieldCheck />, color: 'bg-indigo-500' },
    group_offer_moderator: { one: ':name invites you to be a moderator of a group', icon: <Shield />, color: 'bg-indigo-500' },
    group_offer_owner: { one: ':name offers to hand a group over to you', icon: <Crown />, color: 'bg-amber-500' },
    group_invite: { one: ':name invites you to join a group', many: ':names invite you to join a group', icon: <UserPlus />, color: 'bg-indigo-500' },
    group_offer_accepted: { one: ':name accepted your offer in a group', icon: <UserCheck />, color: 'bg-indigo-500' },
};

/** Puts bold names into a translated sentence in place of its :placeholders. */
function fill(text: string, nodes: Record<string, ReactNode>): ReactNode[] {
    return text
        .split(/(:[a-z]+)/)
        .map((part, i) => <Fragment key={i}>{part.startsWith(':') && part.slice(1) in nodes ? nodes[part.slice(1)] : part}</Fragment>);
}

/** The picture of who did it, with Facebook's round badge: the reaction face on a like, an icon otherwise. */
function Badge({ n }: { n: ClubNotification }) {
    const kind = kinds[n.kind];
    const face = n.reaction && n.reaction !== 'like' && kind?.reacted ? REACTIONS.find((r) => r.type === n.reaction) : null;
    if (face)
        return (
            <span className="absolute -end-1 -bottom-1 flex size-7 items-center justify-center rounded-full bg-white text-[20px] leading-none ring-2 ring-white dark:bg-neutral-900 dark:ring-neutral-900">
                {face.emoji}
            </span>
        );

    return (
        <span
            className={`absolute -end-1 -bottom-1 flex size-7 items-center justify-center rounded-full text-white ring-2 ring-white dark:ring-neutral-900 [&>svg]:size-4 ${kind?.color ?? 'bg-slate-400'}`}
        >
            {kind?.icon ?? <Star />}
        </span>
    );
}

/** One notice: opening it goes to what it is about and marks it read. */
function Row({ n }: { n: ClubNotification }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const kind = kinds[n.kind] ?? { one: ':name', icon: <Star />, color: 'bg-slate-400' };
    const several = Boolean(n.also);
    const reacted = n.reaction && n.reaction !== 'like' ? kind.reacted : undefined;
    const name = (p: Person | null | undefined) => <b className="font-semibold">{p?.name ?? t('Former member')}</b>;
    const others = n.others ?? 0;
    const names = several
        ? others > 0
            ? fill(t(':a, :b and :count others', { count: others }), { a: name(n.actor), b: name(n.also) })
            : fill(t(':a and :b'), { a: name(n.actor), b: name(n.also) })
        : null;
    // A grouped like reads "reacted", as on Facebook, whichever faces were left.
    const text = several ? (reacted?.[1] ?? kind.reacted?.[1] ?? kind.many ?? kind.one) : (reacted?.[0] ?? kind.one);

    return (
        <li>
            {/* A plain link: the server redirects to the post, profile or event. */}
            <a
                href={route('notifications.open', n.id)}
                className="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
            >
                <span className="relative shrink-0 self-start">
                    <UserAvatar person={n.actor} className="size-14" />
                    <Badge n={n} />
                </span>
                <span className="min-w-0 flex-1 text-[15px] leading-[1.3]">
                    <span className={`line-clamp-3 ${n.read ? 'text-muted-foreground' : 'text-slate-900 dark:text-white'}`}>
                        {fill(t(text), { name: name(n.actor), names })}
                        {n.excerpt && (
                            <>
                                {': '}
                                <span dir="auto">«{n.excerpt}»</span>
                            </>
                        )}
                    </span>
                    <span className={`mt-0.5 block text-[13px] ${n.read ? 'text-muted-foreground' : 'text-brand font-semibold'}`}>
                        {shortAgo(n.created_at, locale)}
                    </span>
                </span>
                {!n.read && <span className="bg-brand size-3 shrink-0 rounded-full" />}
            </a>
        </li>
    );
}

/**
 * Notices the Facebook way: "All" and "Unread" on top, then the unread ones under "New",
 * those of today under "Today" and the rest under "Earlier".
 */
export function NotificationList({ notifications, compact = false }: { notifications: ClubNotification[]; compact?: boolean }) {
    const t = useT();
    const [unreadOnly, setUnreadOnly] = useState(false);
    const today = new Date().toDateString();
    const shown = unreadOnly ? notifications.filter((n) => !n.read) : notifications;
    const sections = [
        { title: t('New'), rows: shown.filter((n) => !n.read) },
        { title: t('Today'), rows: shown.filter((n) => n.read && n.created_at && new Date(n.created_at).toDateString() === today) },
        { title: t('Earlier'), rows: shown.filter((n) => n.read && !(n.created_at && new Date(n.created_at).toDateString() === today)) },
    ].filter((s) => s.rows.length > 0);

    return (
        <div>
            <div className="flex gap-1 px-2 pb-1">
                {[
                    { on: false, label: t('All') },
                    { on: true, label: t('Unread') },
                ].map((tab) => (
                    <button
                        key={tab.label}
                        type="button"
                        aria-pressed={unreadOnly === tab.on}
                        onClick={() => setUnreadOnly(tab.on)}
                        className="aria-pressed:bg-brand-soft aria-pressed:text-brand dark:aria-pressed:bg-brand/20 rounded-full px-3 py-1.5 text-[15px] font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                    >
                        {tab.label}
                    </button>
                ))}
            </div>
            {sections.length === 0 && (
                <p className="text-muted-foreground px-3 py-8 text-center text-[15px]">
                    {unreadOnly ? t('You have no unread notifications.') : t('No notifications yet.')}
                </p>
            )}
            {sections.map((section, i) => (
                <section key={section.title}>
                    <div className="flex items-center justify-between px-2 pt-2 pb-1">
                        <h3 className="text-[17px] font-semibold">{section.title}</h3>
                        {compact && i === 0 && (
                            <Link
                                href={route('notifications.index')}
                                className="text-brand rounded-md px-2 py-1 text-[15px] hover:bg-slate-100 dark:hover:bg-neutral-800"
                            >
                                {t('View all')}
                            </Link>
                        )}
                    </div>
                    <ul>
                        {section.rows.map((n) => (
                            <Row key={n.id} n={n} />
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
