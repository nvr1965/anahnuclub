import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Person, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CalendarCheck, Crown, MessageCircle, Shield, ShieldCheck, Star, ThumbsUp, UserCheck, UserPlus } from 'lucide-react';
import { type ReactNode } from 'react';

export interface ClubNotification {
    id: string;
    kind: string;
    actor: Person | null;
    excerpt: string | null;
    read: boolean;
    created_at: string | null;
}

const kinds: Record<string, { text: string; icon: ReactNode; color: string }> = {
    post_liked: { text: ':name liked your post', icon: <ThumbsUp />, color: 'bg-brand' },
    photo_liked: { text: ':name liked your photo', icon: <ThumbsUp />, color: 'bg-brand' },
    video_liked: { text: ':name liked your video', icon: <ThumbsUp />, color: 'bg-brand' },
    post_commented: { text: ':name commented on your post', icon: <MessageCircle />, color: 'bg-green-500' },
    comment_replied: { text: ':name replied to your comment', icon: <MessageCircle />, color: 'bg-green-500' },
    comment_also: { text: ':name also commented on a post you commented on', icon: <MessageCircle />, color: 'bg-green-500' },
    friend_request: { text: ':name sent you a friend request', icon: <UserPlus />, color: 'bg-sky-500' },
    friend_accepted: { text: ':name accepted your friend request', icon: <UserCheck />, color: 'bg-sky-500' },
    event_going: { text: ':name is going to your event', icon: <CalendarCheck />, color: 'bg-red-500' },
    event_interested: { text: ':name is interested in your event', icon: <Star />, color: 'bg-amber-500' },
    group_offer_admin: { text: ':name invites you to be an admin of a group', icon: <ShieldCheck />, color: 'bg-indigo-500' },
    group_offer_moderator: { text: ':name invites you to be a moderator of a group', icon: <Shield />, color: 'bg-indigo-500' },
    group_offer_owner: { text: ':name offers to hand a group over to you', icon: <Crown />, color: 'bg-amber-500' },
    group_offer_accepted: { text: ':name accepted your offer in a group', icon: <UserCheck />, color: 'bg-indigo-500' },
};

/** Notices as a list of rows; each opens what it is about and marks itself read. */
export function NotificationList({ notifications }: { notifications: ClubNotification[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;

    if (notifications.length === 0) return <p className="text-muted-foreground px-3 py-8 text-center text-[15px]">{t('No notifications yet.')}</p>;

    return (
        <ul>
            {notifications.map((n) => {
                const kind = kinds[n.kind] ?? { text: ':name', icon: <Star />, color: 'bg-slate-400' };
                return (
                    <li key={n.id}>
                        {/* A plain link: the server redirects to the post, profile or event. */}
                        <a
                            href={route('notifications.open', n.id)}
                            className={`flex items-start gap-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800 ${n.read ? 'opacity-60 hover:opacity-100' : 'bg-brand/5'}`}
                        >
                            <span className="relative shrink-0">
                                <UserAvatar person={n.actor} className="size-14" />
                                <span
                                    className={`absolute -end-1 -bottom-1 flex size-6 items-center justify-center rounded-full text-white ring-2 ring-white dark:ring-neutral-900 [&>svg]:size-3.5 ${kind.color}`}
                                >
                                    {kind.icon}
                                </span>
                            </span>
                            <span className="min-w-0 flex-1 text-[15px] leading-snug">
                                <span className={n.read ? '' : 'font-medium'}>
                                    {t(kind.text, { name: '\u0000' })
                                        .split('\u0000')
                                        .flatMap((part, i) => (i === 0 ? [part] : [<b key={i}>{n.actor?.name ?? t('Former member')}</b>, part]))}
                                </span>
                                {n.excerpt && (
                                    <span dir="auto" className="text-muted-foreground block truncate text-[13px]">
                                        «{n.excerpt}»
                                    </span>
                                )}
                                <span className={`block text-[13px] ${n.read ? 'text-muted-foreground' : 'text-brand font-semibold'}`}>
                                    {relativeTime(n.created_at, locale)}
                                </span>
                            </span>
                            {!n.read && <span className="bg-brand mt-5 size-3 shrink-0 rounded-full" />}
                        </a>
                    </li>
                );
            })}
        </ul>
    );
}
