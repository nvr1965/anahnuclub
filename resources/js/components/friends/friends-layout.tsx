import { InviteButton } from '@/components/invite-button';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Cake, ChevronRight, ListChecks, UserCheck, UserPlus, Users, UsersRound } from 'lucide-react';
import { type ReactNode } from 'react';

export interface FriendsMenuCounts {
    requests: number;
    friends: number;
    lists: number;
}

export type FriendsSection = 'home' | 'requests' | 'suggestions' | 'all' | 'birthdays' | 'lists';

const items: { key: FriendsSection; route: string; label: string; icon: typeof Users; more?: boolean }[] = [
    { key: 'home', route: 'friends.index', label: 'Home', icon: UsersRound },
    { key: 'requests', route: 'friends.requests', label: 'Friend requests', icon: UserPlus, more: true },
    { key: 'suggestions', route: 'friends.suggestions', label: 'Suggestions', icon: Users, more: true },
    { key: 'all', route: 'friends.all', label: 'All friends', icon: UserCheck, more: true },
    { key: 'birthdays', route: 'friends.birthdays', label: 'Birthdays', icon: Cake },
    { key: 'lists', route: 'friends.lists', label: 'Custom lists', icon: ListChecks, more: true },
];

/** The Friends menu, like Facebook's: home, requests, suggestions, all friends, birthdays and own lists. */
export function FriendsMenu({ active, menu }: { active: FriendsSection; menu: FriendsMenuCounts }) {
    const t = useT();

    return (
        <nav className="space-y-1">
            <h1 className="px-2 pb-2 text-2xl font-bold">{t('Friends')}</h1>
            {items.map(({ key, route: name, label, icon: Icon, more }) => {
                const count = key === 'requests' ? menu.requests : 0;
                const on = key === active;

                return (
                    <Link
                        key={key}
                        href={route(name)}
                        className={`flex items-center gap-3 rounded-lg px-2 py-2 text-[15px] font-medium ${on ? 'bg-black/5 dark:bg-white/10' : 'hover:bg-black/5 dark:hover:bg-white/10'}`}
                    >
                        <span
                            className={`flex size-9 shrink-0 items-center justify-center rounded-full ${on ? 'bg-brand text-white' : 'bg-slate-200 text-slate-800 dark:bg-neutral-700 dark:text-neutral-100'}`}
                        >
                            <Icon className="size-5" />
                        </span>
                        <span className="min-w-0 flex-1">{t(label)}</span>
                        {count > 0 && <span className="rounded-full bg-red-500 px-1.5 text-xs font-bold text-white">{count}</span>}
                        {more && <ChevronRight className="text-muted-foreground size-5 shrink-0 rtl:rotate-180" />}
                    </Link>
                );
            })}
            <div className="px-2 pt-3">
                <InviteButton />
            </div>
        </nav>
    );
}

/** Chips for small screens, where the side menu folds away. */
export function FriendsChips({ active, menu }: { active: FriendsSection; menu: FriendsMenuCounts }) {
    const t = useT();

    return (
        <div className="-mx-3 mb-4 flex gap-2 overflow-x-auto px-3 pb-1 lg:hidden">
            {items.map(({ key, route: name, label }) => (
                <Link
                    key={key}
                    href={route(name)}
                    className={`shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold whitespace-nowrap ${key === active ? 'bg-brand/15 text-brand' : 'bg-slate-200 dark:bg-neutral-800'}`}
                >
                    {t(label)}
                    {key === 'requests' && menu.requests > 0 && <span className="ms-1 text-red-500">{menu.requests}</span>}
                </Link>
            ))}
        </div>
    );
}

export default function FriendsLayout({ active, menu, children }: { active: FriendsSection; menu: FriendsMenuCounts; children: ReactNode }) {
    return (
        <ClubLayout left={<FriendsMenu active={active} menu={menu} />}>
            <FriendsChips active={active} menu={menu} />
            {children}
        </ClubLayout>
    );
}
