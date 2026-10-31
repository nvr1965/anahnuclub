import { FriendsChips, type FriendsMenuCounts } from '@/components/friends/friends-layout';
import { MutualLine, type FriendCard } from '@/components/friends/mutual-line';
import { softButton, useFriendActions, type FriendStatus } from '@/components/friends/use-friend-actions';
import { primary, secondary } from '@/components/person-card';
import { UserAvatar } from '@/components/user-avatar';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MapPin, MessageCircle, UserCheck, UserRound, Users } from 'lucide-react';

type Kind = 'requests' | 'suggestions' | 'all';

interface Preview extends FriendCard {
    cover: string | null;
    status: string | null;
    about: string | null;
    location: string | null;
    joined_at: string | null;
    friends_count: number | null;
    friendship: 'none' | 'friends' | 'sent' | 'received';
    can_message: boolean;
}

interface Props {
    kind: Kind;
    menu: FriendsMenuCounts;
    people: FriendCard[];
    sent?: FriendCard[];
    preview: Preview | null;
}

const titles: Record<Kind, string> = { requests: 'Friend requests', suggestions: 'Suggestions', all: 'All friends' };
const routes: Record<Kind, string> = { requests: 'friends.requests', suggestions: 'friends.suggestions', all: 'friends.all' };

/**
 * Requests, suggestions and all friends, like Facebook's: the people in a column on the left,
 * the one picked shown on the right. On phones the list and the profile take turns.
 */
export default function Browse({ kind, menu, people, sent = [], preview }: Props) {
    const t = useT();
    const actions = useFriendActions(['menu', 'preview']);
    const open = (id: number | null) =>
        router.get(route(routes[kind]), id ? { id } : {}, {
            preserveState: true,
            preserveScroll: () => window.innerWidth >= 1024,
            only: ['preview'],
        });

    const list = <PeopleList kind={kind} menu={menu} people={people} sent={sent} selected={preview?.id ?? null} open={open} actions={actions} />;

    return (
        <ClubLayout left={list}>
            <Head title={t(titles[kind])} />
            <div className={preview ? 'hidden' : 'lg:hidden'}>
                <FriendsChips active={kind} menu={menu} />
                {list}
            </div>
            {preview ? (
                <div>
                    <button onClick={() => open(null)} className="text-brand mb-3 flex items-center gap-1 text-[15px] font-semibold lg:hidden">
                        <ArrowLeft className="size-4 rtl:rotate-180" /> {t(titles[kind])}
                    </button>
                    <ProfilePreview person={preview} status={actions.status[preview.id]} actions={actions} />
                </div>
            ) : (
                <div className="text-muted-foreground hidden min-h-[60vh] flex-col items-center justify-center gap-3 text-center lg:flex">
                    <Users className="size-24 opacity-40" />
                    <p className="text-xl font-bold">{t('Select a person to preview their profile.')}</p>
                </div>
            )}
        </ClubLayout>
    );
}

type Actions = ReturnType<typeof useFriendActions>;

function PeopleList({
    kind,
    menu,
    people,
    sent,
    selected,
    open,
    actions,
}: {
    kind: Kind;
    menu: FriendsMenuCounts;
    people: FriendCard[];
    sent: FriendCard[];
    selected: number | null;
    open: (id: number) => void;
    actions: Actions;
}) {
    const t = useT();
    const shown = people.filter((p) => actions.status[p.id] !== 'dismissed');
    const shownSent = sent.filter((p) => actions.status[p.id] !== 'cancelled');

    return (
        <div>
            <div className="mb-3 flex items-center gap-2">
                <Link href={route('friends.index')} className="rounded-full p-2 hover:bg-black/5 dark:hover:bg-white/10" aria-label={t('Friends')}>
                    <ArrowLeft className="size-5 rtl:rotate-180" />
                </Link>
                <div>
                    <Link href={route('friends.index')} className="text-muted-foreground text-[13px] hover:underline">
                        {t('Friends')}
                    </Link>
                    <h1 className="text-2xl leading-tight font-bold">{t(titles[kind])}</h1>
                </div>
            </div>
            <p className="mb-2 px-1 text-[15px] font-semibold">
                {kind === 'requests'
                    ? t(':count friend requests', { count: menu.requests })
                    : kind === 'all'
                      ? t(':count friends', { count: menu.friends })
                      : t('People you may know')}
            </p>
            {shown.length === 0 && (
                <p className="text-muted-foreground px-1 py-4 text-[15px]">
                    {kind === 'requests' ? t('No new requests.') : kind === 'all' ? t('No friends yet.') : t('No suggestions right now.')}
                </p>
            )}
            <div className="space-y-1">
                {shown.map((person) => (
                    <Row key={person.id} person={person} active={person.id === selected} onOpen={() => open(person.id)}>
                        <RowActions kind={kind} person={person} status={actions.status[person.id]} actions={actions} />
                    </Row>
                ))}
            </div>
            {kind === 'requests' && shownSent.length > 0 && (
                <>
                    <p className="mt-6 mb-2 px-1 text-[15px] font-semibold">{t('Sent requests')}</p>
                    <div className="space-y-1">
                        {shownSent.map((person) => (
                            <Row key={person.id} person={person} active={person.id === selected} onOpen={() => open(person.id)}>
                                <button className={secondary} onClick={() => actions.cancel(person)}>
                                    {t('Cancel request')}
                                </button>
                            </Row>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function Row({ person, active, onOpen, children }: { person: FriendCard; active: boolean; onOpen: () => void; children: React.ReactNode }) {
    return (
        <div className={`flex gap-3 rounded-lg p-2 ${active ? 'bg-black/5 dark:bg-white/10' : 'hover:bg-black/5 dark:hover:bg-white/10'}`}>
            <button onClick={onOpen} className="shrink-0">
                <UserAvatar person={person} className="size-15" />
            </button>
            <div className="min-w-0 flex-1 space-y-1.5">
                <button onClick={onOpen} className="block w-full text-start">
                    <span dir="auto" className="block truncate text-[15px] font-semibold">
                        {person.name}
                    </span>
                    <MutualLine person={person} />
                </button>
                {children}
            </div>
        </div>
    );
}

function RowActions({ kind, person, status, actions }: { kind: Kind; person: Person; status?: FriendStatus; actions: Actions }) {
    const t = useT();
    if (kind === 'all') return null;
    if (status === 'accepted') return <p className="text-muted-foreground text-sm">{t('Request accepted')}</p>;
    if (status === 'declined') return <p className="text-muted-foreground text-sm">{t('Request removed')}</p>;
    if (kind === 'requests') {
        return (
            <>
                <button className={primary} onClick={() => actions.accept(person)}>
                    {t('Confirm')}
                </button>
                <button className={secondary} onClick={() => actions.decline(person)}>
                    {t('Delete')}
                </button>
            </>
        );
    }
    if (status === 'sent') {
        return (
            <button className={secondary} onClick={() => actions.cancel(person)}>
                {t('Cancel request')}
            </button>
        );
    }

    return (
        <>
            <button className={primary} onClick={() => actions.add(person)}>
                {t('Add friend')}
            </button>
            <button className={secondary} onClick={() => actions.dismiss(person)}>
                {t('Delete')}
            </button>
        </>
    );
}

/** A short profile on the right: cover, picture, friends in common and the friendship buttons. */
function ProfilePreview({ person, status, actions }: { person: Preview; status?: FriendStatus; actions: Actions }) {
    const t = useT();
    const button = 'flex items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold';

    return (
        <div className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
            <div className="from-header-from to-brand aspect-[3/1] w-full bg-gradient-to-r">
                {person.cover && <img src={person.cover} alt="" className="size-full object-cover" />}
            </div>
            <div className="px-4 pb-5 sm:px-6">
                <div className="-mt-12 flex flex-col gap-2 sm:-mt-16 sm:flex-row sm:items-end sm:gap-4">
                    <div className="self-start">
                        <UserAvatar person={person} className="size-24 ring-4 ring-white sm:size-32 dark:ring-neutral-900" />
                    </div>
                    <div className="min-w-0 flex-1 pb-1">
                        <Link href={route('people.show', person.id)} dir="auto" className="block text-2xl font-bold hover:underline sm:text-3xl">
                            {person.name}
                        </Link>
                        {person.friends_count !== null && (
                            <p className="text-muted-foreground text-[15px]">{t(':count friends', { count: person.friends_count })}</p>
                        )}
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    {person.friendship === 'friends' ? (
                        <span className={`${button} bg-slate-200 dark:bg-neutral-800`}>
                            <UserCheck className="size-4" /> {t('Friends')}
                        </span>
                    ) : person.friendship === 'received' ? (
                        <>
                            <button className={`${button} bg-brand hover:bg-brand-dark text-white`} onClick={() => actions.accept(person)}>
                                {t('Confirm')}
                            </button>
                            <button className={`${button} bg-slate-200 dark:bg-neutral-800`} onClick={() => actions.decline(person)}>
                                {t('Delete')}
                            </button>
                        </>
                    ) : person.friendship === 'sent' ? (
                        <button className={`${button} bg-slate-200 dark:bg-neutral-800`} onClick={() => actions.cancel(person)}>
                            {t('Cancel request')}
                        </button>
                    ) : (
                        <button className={`${button} bg-brand hover:bg-brand-dark text-white`} onClick={() => actions.add(person)}>
                            {t('Add friend')}
                        </button>
                    )}
                    {person.can_message && (
                        <button
                            className={`${button} ${softButton.replace('w-full', '')}`}
                            onClick={() => router.post(route('messages.start', person.id))}
                        >
                            <MessageCircle className="size-4" /> {t('Message')}
                        </button>
                    )}
                    <Link href={route('people.show', person.id)} className={`${button} bg-slate-200 dark:bg-neutral-800`}>
                        <UserRound className="size-4" /> {t('View profile')}
                    </Link>
                    {status === 'accepted' && <span className="text-muted-foreground self-center text-sm">{t('Request accepted')}</span>}
                </div>

                {(person.status || person.about || person.location) && (
                    <div className="mt-5 space-y-2 border-t pt-4 text-[15px] dark:border-neutral-800">
                        {person.status && (
                            <p dir="auto" className="font-medium">
                                {person.status}
                            </p>
                        )}
                        {person.about && (
                            <p dir="auto" className="line-clamp-4 whitespace-pre-line">
                                {person.about}
                            </p>
                        )}
                        {person.location && (
                            <p className="text-muted-foreground flex items-center gap-2">
                                <MapPin className="size-4 shrink-0" /> <span dir="auto">{person.location}</span>
                            </p>
                        )}
                    </div>
                )}

                {!!person.mutual && (
                    <div className="mt-5 border-t pt-4 dark:border-neutral-800">
                        <p className="mb-3 font-semibold">
                            {t(person.mutual === 1 ? '1 mutual friend' : ':count mutual friends', { count: person.mutual })}
                        </p>
                        <div className="grid grid-cols-4 gap-3 sm:grid-cols-8">
                            {(person.mutual_people ?? []).map((p) => (
                                <Link key={p.id} href={route('people.show', p.id)} className="text-center text-xs">
                                    <UserAvatar person={p} className="mx-auto size-14" />
                                    <span dir="auto" className="mt-1 block truncate">
                                        {p.name}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
