import { PersonCard, primary, secondary } from '@/components/person-card';
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

                <Section
                    title={t('People you may know')}
                    more={
                        <Link href={route('people.index')} className="text-brand text-[15px] font-semibold hover:underline">
                            {t('Show all')}
                        </Link>
                    }
                >
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

function Section({ title, count, more, children }: { title: string; count?: number; more?: ReactNode; children: ReactNode }) {
    return (
        <section>
            <div className="mb-3 flex items-baseline justify-between gap-2">
                <h2 className="text-xl font-bold">
                    {title} {count !== undefined && <span className="text-muted-foreground font-normal">{count}</span>}
                </h2>
                {more}
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">{children}</div>
        </section>
    );
}
