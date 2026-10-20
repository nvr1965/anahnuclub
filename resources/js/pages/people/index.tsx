import { InviteButton } from '@/components/invite-button';
import { PersonCard, primary, secondary } from '@/components/person-card';
import AppLayout from '@/layouts/app-layout';
import { countryFlag, countryName } from '@/lib/countries';
import { useT } from '@/lib/i18n';
import { type Paginated, type Person, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Member = Person & { friendship: 'none' | 'friends' | 'sent' | 'received' };

interface Props {
    people: Paginated<Member>;
    total: number;
    q: string;
    country: string | null;
    lang: string | null;
    countries: Record<string, number>;
    langs: Record<string, number>;
}

export default function People({ people, total, q, country, lang, countries, langs }: Props) {
    const t = useT();
    const { locale, languages } = usePage<SharedData>().props;
    const params = (patch: Record<string, string | null>) =>
        Object.fromEntries(Object.entries({ q: query || null, country, lang, ...patch }).filter(([, v]) => v));
    const filter = (patch: Record<string, string | null>) => router.get(route('people.index'), params(patch), { preserveState: true, replace: true });
    const select = 'focus:ring-brand rounded-full bg-white py-2 ps-3 pe-8 text-[15px] shadow-sm outline-none focus:ring-2 dark:bg-neutral-900';
    const [query, setQuery] = useState(q);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => filter({}), 300);
        return () => clearTimeout(timer);
    }, [query]);

    const add = (person: Member) => router.post(route('friends.store', person.id), {}, { preserveScroll: true });
    const remove = (person: Member) => router.delete(route('friends.destroy', person.id), { preserveScroll: true });

    return (
        <AppLayout>
            <Head title={t('All members')} />
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-bold">
                            {t('All members')} <span className="text-muted-foreground font-normal">{total}</span>
                        </h1>
                        <InviteButton />
                    </div>
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

                <div className="flex flex-wrap gap-2">
                    <select
                        aria-label={t('Country')}
                        className={select}
                        value={country ?? ''}
                        onChange={(e) => filter({ country: e.target.value || null })}
                    >
                        <option value="">{t('All countries')}</option>
                        {Object.entries(countries)
                            .map(([code, n]) => [code, countryName(code, locale), n] as const)
                            .sort((a, b) => a[1].localeCompare(b[1], locale))
                            .map(([code, name, n]) => (
                                <option key={code} value={code}>
                                    {countryFlag(code)} {name} ({n})
                                </option>
                            ))}
                    </select>
                    <select
                        aria-label={t('Language')}
                        className={select}
                        value={lang ?? ''}
                        onChange={(e) => filter({ lang: e.target.value || null })}
                    >
                        <option value="">{t('All languages')}</option>
                        {Object.entries(langs).map(([code, n]) => (
                            <option key={code} value={code}>
                                {languages.find((l) => l.code === code)?.native_name ?? code} ({n})
                            </option>
                        ))}
                    </select>
                    {(country || lang) && (
                        <button
                            type="button"
                            className="text-brand px-2 text-sm font-semibold hover:underline"
                            onClick={() => filter({ country: null, lang: null })}
                        >
                            {t('Reset filters')}
                        </button>
                    )}
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
