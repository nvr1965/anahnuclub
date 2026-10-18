import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface Props {
    requests: Person[];
    sent: Person[];
    friends: Person[];
    suggestions: Person[];
}

const primary = 'bg-brand hover:bg-brand-dark w-full rounded-md py-1.5 text-sm font-semibold text-white';
const secondary = 'w-full rounded-md bg-slate-200 py-1.5 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700';

export default function Friends({ requests, sent, friends, suggestions }: Props) {
    const t = useT();
    const add = (person: Person) => router.post(route('friends.store', person.id), {}, { preserveScroll: true });
    const remove = (person: Person) => router.delete(route('friends.destroy', person.id), { preserveScroll: true });

    return (
        <AppLayout>
            <Head title={t('Friends')} />
            <div className="space-y-6">
                {requests.length > 0 && (
                    <Section title={t('Friend requests')} count={requests.length}>
                        {requests.map((person) => (
                            <PersonCard key={person.id} person={person}>
                                <button className={primary} onClick={() => add(person)}>
                                    {t('Confirm')}
                                </button>
                                <button className={secondary} onClick={() => remove(person)}>
                                    {t('Delete')}
                                </button>
                            </PersonCard>
                        ))}
                    </Section>
                )}

                <Section title={t('People you may know')}>
                    {suggestions.map((person) => (
                        <PersonCard key={person.id} person={person}>
                            <button className={primary} onClick={() => add(person)}>
                                {t('Add friend')}
                            </button>
                        </PersonCard>
                    ))}
                </Section>

                {sent.length > 0 && (
                    <Section title={t('Sent requests')} count={sent.length}>
                        {sent.map((person) => (
                            <PersonCard key={person.id} person={person}>
                                <button className={secondary} onClick={() => remove(person)}>
                                    {t('Cancel request')}
                                </button>
                            </PersonCard>
                        ))}
                    </Section>
                )}

                <Section title={t('All friends')} count={friends.length}>
                    {friends.length === 0 && <p className="text-muted-foreground col-span-full text-[15px]">{t('No friends yet.')}</p>}
                    {friends.map((person) => (
                        <PersonCard key={person.id} person={person}>
                            <button className={secondary} onClick={() => router.post(route('messages.start', person.id))}>
                                {t('Message')}
                            </button>
                        </PersonCard>
                    ))}
                </Section>
            </div>
        </AppLayout>
    );
}

function Section({ title, count, children }: { title: string; count?: number; children: ReactNode }) {
    return (
        <section>
            <h2 className="mb-3 text-xl font-bold">
                {title} {count !== undefined && <span className="text-muted-foreground font-normal">{count}</span>}
            </h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">{children}</div>
        </section>
    );
}

function PersonCard({ person, children }: { person: Person; children: ReactNode }) {
    return (
        <div className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
            <Link href={route('people.show', person.id)} className="block">
                {person.avatar ? (
                    <img src={person.avatar} alt="" loading="lazy" className="aspect-square w-full object-cover" />
                ) : (
                    <UserAvatar person={person} className="aspect-square size-auto w-full rounded-none" fallbackClassName="rounded-none text-4xl" />
                )}
            </Link>
            <div className="space-y-1.5 p-3">
                <Link href={route('people.show', person.id)} dir="auto" className="block truncate text-[15px] font-semibold hover:underline">
                    {person.name}
                </Link>
                {children}
            </div>
        </div>
    );
}
