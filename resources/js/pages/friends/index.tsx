import FriendsLayout, { type FriendsMenuCounts } from '@/components/friends/friends-layout';
import { type FriendCard, MutualLine } from '@/components/friends/mutual-line';
import { softButton, useFriendActions } from '@/components/friends/use-friend-actions';
import { InviteButton } from '@/components/invite-button';
import { PersonCard, primary, secondary } from '@/components/person-card';
import { useT } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { UsersRound } from 'lucide-react';
import { type ReactNode, useState } from 'react';

/** Two rows of the grid on a wide screen; "See more" adds as many. */
const STEP = 10;

interface Props {
    menu: FriendsMenuCounts;
    requests: FriendCard[];
    suggestions: FriendCard[];
}

/** Friends home, like Facebook's: requests waiting for an answer, then a grid of people you may know. */
export default function Friends({ menu, requests, suggestions }: Props) {
    const t = useT();
    const { status, add, accept, cancel, decline, dismiss } = useFriendActions(['menu']);
    const [visible, setVisible] = useState(STEP);
    const remaining = suggestions.filter((p) => status[p.id] !== 'dismissed');
    const shownSuggestions = remaining.slice(0, visible);

    return (
        <FriendsLayout active="home" menu={menu}>
            <Head title={t('Friends')} />
            <div className="space-y-8">
                <div className="flex flex-wrap items-center justify-between gap-3 lg:hidden">
                    <h1 className="text-2xl font-bold">{t('Friends')}</h1>
                    <InviteButton />
                </div>
                {requests.length > 0 && (
                    <Section title={t('Friend requests')} count={menu.requests} href={route('friends.requests')}>
                        {requests.map((person) => (
                            <PersonCard key={person.id} person={person}>
                                <MutualLine person={person} />
                                {status[person.id] === 'accepted' ? (
                                    <p className="text-muted-foreground py-1.5 text-center text-sm">{t('Request accepted')}</p>
                                ) : status[person.id] === 'declined' ? (
                                    <p className="text-muted-foreground py-1.5 text-center text-sm">{t('Request removed')}</p>
                                ) : (
                                    <>
                                        <button className={primary} onClick={() => accept(person)}>
                                            {t('Confirm')}
                                        </button>
                                        <button className={secondary} onClick={() => decline(person)}>
                                            {t('Delete')}
                                        </button>
                                    </>
                                )}
                            </PersonCard>
                        ))}
                    </Section>
                )}

                <Section title={t('People you may know')} href={route('friends.suggestions')}>
                    {shownSuggestions.length === 0 && (
                        <div className="col-span-full flex flex-col items-center gap-3 rounded-lg bg-white p-8 text-center shadow-sm dark:bg-neutral-900">
                            <UsersRound className="text-muted-foreground size-10" />
                            <p className="text-muted-foreground text-[15px]">{t('No suggestions right now. Invite your friends to the club.')}</p>
                            <InviteButton />
                        </div>
                    )}
                    {shownSuggestions.map((person) => (
                        <PersonCard key={person.id} person={person}>
                            <MutualLine person={person} />
                            {status[person.id] === 'sent' ? (
                                <>
                                    <button className={`${softButton} cursor-default opacity-70`} disabled>
                                        {t('Request sent')}
                                    </button>
                                    <button className={secondary} onClick={() => cancel(person)}>
                                        {t('Cancel')}
                                    </button>
                                </>
                            ) : (
                                <>
                                    <button className={softButton} onClick={() => add(person)}>
                                        {t('Add friend')}
                                    </button>
                                    <button className={secondary} onClick={() => dismiss(person)}>
                                        {t('Delete')}
                                    </button>
                                </>
                            )}
                        </PersonCard>
                    ))}
                </Section>
                {remaining.length > 0 && (
                    <div className="-mt-4">
                        {remaining.length > visible ? (
                            <button className={secondary} onClick={() => setVisible((n) => n + STEP)}>
                                {t('See more')}
                            </button>
                        ) : (
                            <Link href={route('friends.suggestions')} className={`${secondary} block text-center`}>
                                {t('See more')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </FriendsLayout>
    );
}

function Section({ title, count, href, children }: { title: string; count?: number; href: string; children: ReactNode }) {
    const t = useT();

    return (
        <section>
            <div className="mb-3 flex items-baseline justify-between gap-2">
                <h2 className="text-xl font-bold">
                    {title} {!!count && <span className="text-red-500">{count}</span>}
                </h2>
                <Link href={href} className="text-brand text-[15px] font-semibold hover:underline">
                    {t('See all')}
                </Link>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">{children}</div>
        </section>
    );
}
