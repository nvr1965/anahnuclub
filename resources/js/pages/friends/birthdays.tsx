import FriendsLayout, { type FriendsMenuCounts } from '@/components/friends/friends-layout';
import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Person, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Cake } from 'lucide-react';

interface Birthday extends Person {
    birthday: string;
    age: number;
    days: number;
}

/** Friends' birthdays: today, the coming month, then later by month. */
export default function Birthdays({ menu, people }: { menu: FriendsMenuCounts; people: Birthday[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const today = people.filter((p) => p.days === 0);
    const soon = people.filter((p) => p.days > 0 && p.days <= 30);
    const later = people.filter((p) => p.days > 30);
    const monthOf = (date: string) => {
        const text = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(new Date(date + 'T12:00:00'));
        return text.charAt(0).toUpperCase() + text.slice(1);
    };
    const dayOf = (date: string) =>
        new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', weekday: 'long' }).format(new Date(date + 'T12:00:00'));
    const months = later.reduce<Record<string, Birthday[]>>((acc, p) => {
        (acc[monthOf(p.birthday)] ??= []).push(p);
        return acc;
    }, {});

    const row = (p: Birthday, showDay = true) => (
        <div key={p.id} className="flex items-center gap-3 py-2">
            <Link href={route('people.show', p.id)}>
                <UserAvatar person={p} className="size-14" />
            </Link>
            <div className="min-w-0 flex-1">
                <Link href={route('people.show', p.id)} dir="auto" className="block truncate text-[15px] font-semibold hover:underline">
                    {p.name}
                </Link>
                <p className="text-muted-foreground text-[13px]">
                    {showDay && <>{dayOf(p.birthday)} · </>}
                    {t('Turns :age', { age: p.age })}
                </p>
            </div>
            {p.days === 0 && (
                <button
                    className="bg-brand hover:bg-brand-dark rounded-md px-3 py-1.5 text-sm font-semibold text-white"
                    onClick={() => router.post(route('messages.start', p.id))}
                >
                    {t('Congratulate')}
                </button>
            )}
        </div>
    );

    const card = 'rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900';

    return (
        <FriendsLayout active="birthdays" menu={menu}>
            <Head title={t('Birthdays')} />
            <div className="mx-auto max-w-[680px] space-y-4">
                <h1 className="text-2xl font-bold">{t('Birthdays')}</h1>
                {people.length === 0 && (
                    <div className={`${card} text-muted-foreground flex flex-col items-center gap-3 py-10 text-center`}>
                        <Cake className="size-10" />
                        <p className="text-[15px]">{t("Your friends' birthdays will appear here when they share them in their profile.")}</p>
                    </div>
                )}
                {today.length > 0 && (
                    <section className={card}>
                        <h2 className="mb-1 flex items-center gap-2 text-lg font-bold">
                            <Cake className="size-5 text-pink-500" /> {t("Today's birthdays")}
                        </h2>
                        {today.map((p) => row(p, false))}
                    </section>
                )}
                {soon.length > 0 && (
                    <section className={card}>
                        <h2 className="mb-1 text-lg font-bold">{t('Upcoming birthdays')}</h2>
                        {soon.map((p) => row(p))}
                    </section>
                )}
                {Object.entries(months).map(([month, list]) => (
                    <section key={month} className={card}>
                        <h2 className="mb-1 text-lg font-bold">{month}</h2>
                        {list.map((p) => row(p))}
                    </section>
                ))}
            </div>
        </FriendsLayout>
    );
}
