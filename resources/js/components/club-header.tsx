import { LanguageSwitcher } from '@/components/language-switcher';
import { NotificationBell } from '@/components/notification-bell';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, House, type LucideIcon, MessageCircle, Search, UserRound, Users } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

/** Tile colours, written out in full so Tailwind keeps them: soft when idle, filled when the section is open. */
const tones = {
    brand: {
        idle: 'border-brand/25 bg-brand-soft text-brand dark:border-brand/40 dark:bg-brand/15',
        active: 'border-brand bg-brand text-white',
        label: 'text-brand',
    },
    emerald: {
        idle: 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
        active: 'border-emerald-600 bg-emerald-600 text-white',
        label: 'text-emerald-700 dark:text-emerald-300',
    },
    violet: {
        idle: 'border-violet-200 bg-violet-50 text-violet-600 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-300',
        active: 'border-violet-600 bg-violet-600 text-white',
        label: 'text-violet-700 dark:text-violet-300',
    },
    sky: {
        idle: 'border-sky-200 bg-sky-50 text-sky-600 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-300',
        active: 'border-sky-600 bg-sky-600 text-white',
        label: 'text-sky-700 dark:text-sky-300',
    },
    amber: {
        idle: 'border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300',
        active: 'border-amber-600 bg-amber-600 text-white',
        label: 'text-amber-700 dark:text-amber-300',
    },
};

type Tone = keyof typeof tones;

/** A rounded icon tile with its name underneath, as on nofinfo. */
function Tile({
    icon: Icon,
    label,
    tone,
    active = false,
    badge = 0,
}: {
    icon: LucideIcon;
    label: string;
    tone: Tone;
    active?: boolean;
    badge?: number;
}) {
    const c = tones[tone];
    return (
        <>
            <span className={`relative flex h-9 w-10 items-center justify-center rounded-xl border transition-colors ${active ? c.active : c.idle}`}>
                <Icon className="size-5" />
                <Badge count={badge} className="-end-2 -top-1.5" />
            </span>
            <span className={`w-full truncate text-center text-[11px] leading-tight tracking-tight ${c.label} ${active ? 'font-semibold' : ''}`}>
                {label}
            </span>
        </>
    );
}

const tileLink =
    'flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-0.5 py-1 outline-none hover:bg-slate-50 sm:w-[76px] sm:flex-none dark:hover:bg-neutral-800';

/** Facebook-style top bar: logo and search at the start, section tiles with their names in the middle, account at the end. */
export function ClubHeader() {
    const t = useT();
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const [q, setQ] = useState(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '');

    const counts = page.props.counts;
    const tabs: { title: string; href: string; match: string; icon: LucideIcon; tone: Tone; badge: number }[] = [
        { title: t('Feed'), href: route('dashboard'), match: '/feed', icon: House, tone: 'brand', badge: 0 },
        {
            title: t('Friends'),
            href: route('friends.index'),
            match: '/friends',
            icon: UserRound,
            tone: 'emerald',
            badge: counts?.friend_requests ?? 0,
        },
        { title: t('Groups'), href: route('groups.index'), match: '/groups', icon: Users, tone: 'violet', badge: 0 },
        { title: t('Messages'), href: route('messages.index'), match: '/messages', icon: MessageCircle, tone: 'sky', badge: counts?.messages ?? 0 },
    ];

    // New messages and friend requests show up without reloading the page.
    useEffect(() => {
        if (!auth.user) return;
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['counts'] });
        }, 30000);
        return () => clearInterval(timer);
    }, [auth.user?.id]);

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        if (q.trim()) router.get(route('search'), { q: q.trim() });
    };

    const tiles = auth.user && (
        <>
            {tabs.map((tab) => (
                <Link key={tab.match} href={tab.href} title={tab.title} className={tileLink}>
                    <Tile icon={tab.icon} label={tab.title} tone={tab.tone} active={page.url.startsWith(tab.match)} badge={tab.badge} />
                </Link>
            ))}
            <NotificationBell className={tileLink}>
                <Tile icon={Bell} label={t('Notifications')} tone="amber" badge={counts?.notifications ?? 0} />
            </NotificationBell>
        </>
    );

    return (
        <header className="sticky top-0 z-40 bg-white shadow-sm dark:bg-neutral-900">
            <div className="from-header-from via-brand to-header-to h-1 bg-gradient-to-r" />
            <div className="grid h-12 grid-cols-[minmax(0,1fr)_auto] items-center gap-1.5 px-2 sm:h-[68px] sm:grid-cols-[auto_1fr_auto] sm:gap-3 sm:px-3 lg:grid-cols-[1fr_auto_1fr]">
                <div className="flex min-w-0 items-center gap-1.5 sm:gap-2">
                    <Link href={auth.user ? route('dashboard') : route('home')} className="shrink-0" aria-label="anahnu.club">
                        <img src="/images/logo-mark.png" alt="" className="size-9 rounded-full sm:size-10" />
                    </Link>
                    {auth.user && (
                        <form onSubmit={search} className="flex min-w-0 items-center gap-2 rounded-full bg-slate-100 px-3 py-2 dark:bg-neutral-800">
                            <Search className="text-muted-foreground size-4 shrink-0" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('Search')}
                                aria-label={t('Search anahnu.club')}
                                className="w-full min-w-0 bg-transparent text-sm outline-none sm:w-36 lg:w-48"
                            />
                        </form>
                    )}
                </div>

                <nav className="hidden items-center justify-center gap-1 sm:flex">{tiles}</nav>

                <div className="flex items-center justify-end gap-1.5 sm:gap-2">
                    <LanguageSwitcher className="rounded-full bg-slate-100 px-3 py-2 max-sm:px-2.5 dark:bg-neutral-800 max-sm:[&>span:nth-child(2)]:hidden [&>svg]:hidden sm:[&>svg]:block" />
                    {auth.user ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="flex flex-col items-center gap-1 rounded-lg outline-none sm:w-14">
                                <Avatar className="size-9">
                                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                                <span className="hidden w-full truncate text-center text-[11px] leading-tight text-slate-600 sm:block dark:text-neutral-300">
                                    {t('Menu')}
                                </span>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        <Link href={route('login')} className="bg-brand hover:bg-brand-dark rounded-lg px-4 py-2 text-sm font-medium text-white">
                            {t('Log in')}
                        </Link>
                    )}
                </div>
            </div>
            {/* On phones the tiles get a row of their own under the logo, so every name fits. */}
            {auth.user && (
                <nav className="flex items-start justify-around gap-0.5 border-t border-slate-100 px-1 pb-1 sm:hidden dark:border-neutral-800">
                    {tiles}
                </nav>
            )}
        </header>
    );
}

function Badge({ count, className }: { count: number; className: string }) {
    if (count <= 0) return null;

    return (
        <span
            className={`absolute flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white ${className}`}
        >
            {count > 99 ? '99+' : count}
        </span>
    );
}
