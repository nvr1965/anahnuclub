import { PersonCard, primary, secondary } from '@/components/person-card';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type Paginated, type Person } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Member = Person & { friendship: 'none' | 'friends' | 'sent' | 'received' };

interface Props {
    people: Paginated<Member>;
    total: number;
    q: string;
}

export default function People({ people, total, q }: Props) {
    const t = useT();
    const [query, setQuery] = useState(q);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => router.get(route('people.index'), query ? { q: query } : {}, { preserveState: true, replace: true }), 300);
        return () => clearTimeout(timer);
    }, [query]);

    const add = (person: Member) => router.post(route('friends.store', person.id), {}, { preserveScroll: true });
    const remove = (person: Member) => router.delete(route('friends.destroy', person.id), { preserveScroll: true });

    return (
        <AppLayout>
            <Head title={t('All members')} />
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold">
                        {t('All members')} <span className="text-muted-foreground font-normal">{total}</span>
                    </h1>
                    <label className="relative w-full sm:w-64">
                        <Search className="text-muted-foreground absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('Search by name')}
                            className="focus:ring-brand w-full rounded-full bg-white py-2 ps-9 pe-3 text-[15px] shadow-sm outline-none focus:ring-2 dark:bg-neutral-900"
                        />
                    </label>
                </div>

                {people.data.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-center text-[15px]">{t('Nobody found.')}</p>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        {people.data.map((person) => (
                            <PersonCard key={person.id} person={person}>
                                {person.friendship === 'none' && (
                                    <button className={primary} onClick={() => add(person)}>
                                        {t('Add friend')}
                                    </button>
                                )}
                                {person.friendship === 'received' && (
                                    <button className={primary} onClick={() => add(person)}>
                                        {t('Confirm')}
                                    </button>
                                )}
                                {person.friendship === 'sent' && (
                                    <button className={secondary} onClick={() => remove(person)}>
                                        {t('Cancel request')}
                                    </button>
                                )}
                                {person.friendship === 'friends' && (
                                    <button className={secondary} onClick={() => router.post(route('messages.start', person.id))}>
                                        {t('Message')}
                                    </button>
                                )}
                            </PersonCard>
                        ))}
                    </div>
                )}

                <nav className="text-brand flex justify-between px-1 text-sm font-medium">
                    {people.links.prev ? <Link href={people.links.prev}>{t('Back')}</Link> : <span />}
                    {people.links.next && <Link href={people.links.next}>{t('More')}</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
