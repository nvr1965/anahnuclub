import { LanguageSwitcher } from '@/components/language-switcher';
import { MessengerMenu } from '@/components/messenger-menu';
import { NotificationBell } from '@/components/notification-bell';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useT } from '@/lib/i18n';
import { onFreshNews } from '@/lib/push';
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
    /** Stream and Showcase: plain icons in the text colour, the open section in the accent colour. */
    mono: {
        idle: 'border-transparent text-slate-800 dark:text-neutral-100',
        active: 'border-transparent text-brand',
        label: 'text-slate-600 dark:text-neutral-300',
    },
    /** Community: white icons on the blue bar. */
    onblue: {
        idle: 'border-transparent text-white/85',
        active: 'border-white/25 bg-white/20 text-white',
        label: 'text-white/85',
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
    const { skin } = usePage<SharedData>().props;
    const c = tones[skin === 'stream' || skin === 'showcase' ? 'mono' : skin === 'community' ? 'onblue' : tone];
    return (
        <>
            <span className={`relative flex h-9 w-10 items-center justify-center rounded-xl border transition-colors ${active ? c.active : c.idle}`}>
                <Icon className="size-5" />
                <Badge count={badge} className="-end-2 -top-1.5" />
            </span>
            <span className={`w-full truncate text-center text-[0.6875rem] leading-tight tracking-tight ${c.label} ${active ? 'font-semibold' : ''}`}>
                {label}
            </span>
        </>
    );
}

const tileLink =
    'flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-0.5 py-1 outline-none hover:bg-slate-50 sm:w-[76px] sm:flex-none dark:hover:bg-neutral-800';

/** Facebook-style top bar: logo and search at the start, section tiles with their names in the middle, account at the end. */
export function ClubHeader({ className = '' }: { className?: string }) {
    const t = useT();
    const page = usePage<SharedData>();
    const { auth, skin } = page.props;
    const flat = skin === 'stream' || skin === 'showcase';
    // Community: a solid blue bar. Forum: name and a wide search, chats and notifications as round buttons, no tiles on wide screens.
    const blue = skin === 'community';
    const forum = skin === 'forum';
    const field = blue ? 'bg-white/15 text-white placeholder:text-white/70' : 'bg-slate-100 dark:bg-neutral-800';
    const round = 'relative flex size-10 items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-neutral-800';
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
    ];

    // New messages and friend requests show up without reloading the page.
    useEffect(() => {
        if (!auth.user) return;
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['counts'] });
        }, 30000);
        // A push (a new message), the tab back in front or the connection back: the badges at once.
        const stop = onFreshNews(() => router.reload({ only: ['counts'] }));
        return () => {
            clearInterval(timer);
            stop();
        };
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
            {/* Messages and notifications open a panel under the tile, as on Facebook. */}
            <MessengerMenu className={tileLink}>
                <Tile icon={MessageCircle} label={t('Messages')} tone="sky" active={page.url.startsWith('/messages')} badge={counts?.messages ?? 0} />
            </MessengerMenu>
            <NotificationBell className={tileLink}>
                <Tile icon={Bell} label={t('Notifications')} tone="amber" badge={counts?.notifications ?? 0} />
            </NotificationBell>
        </>
    );

    return (
        <header
            className={`sticky top-0 z-40 ${blue ? 'bg-brand text-white' : forum ? 'border-b border-slate-200 bg-white dark:border-neutral-800 dark:bg-neutral-950' : 'bg-white shadow-sm dark:bg-neutral-900'} ${className}`}
        >
            {!blue && !forum && <div className="from-header-from via-brand to-header-to h-1 bg-gradient-to-r" />}
            <div
                className={`grid h-12 grid-cols-[minmax(0,1fr)_auto] items-center gap-1.5 px-2 sm:gap-3 sm:px-3 ${forum ? 'sm:h-14 sm:grid-cols-[auto_1fr_auto] lg:grid-cols-[1fr_minmax(0,640px)_1fr]' : 'sm:h-[4.25rem] sm:grid-cols-[auto_1fr_auto] lg:grid-cols-[1fr_auto_1fr]'}`}
            >
                <div className="flex min-w-0 items-center gap-1.5 sm:gap-2">
                    <Link href={auth.user ? route('dashboard') : route('home')} className="shrink-0" aria-label="anahnu.club">
                        <img src="/images/logo-mark.png" alt="" className="size-9 rounded-full sm:size-10" />
                    </Link>
                    {(forum || blue) && (
                        <Link
                            href={auth.user ? route('dashboard') : route('home')}
                            className="text-brand hidden text-xl font-bold tracking-tight lg:block"
                        >
                            <span className={blue ? 'text-white' : ''}>anahnu.club</span>
                        </Link>
                    )}
                    {auth.user && (
                        <form
                            onSubmit={search}
                            className={`flex min-w-0 items-center gap-2 rounded-full px-3 py-2 ${field} ${forum ? 'sm:hidden' : ''}`}
                        >
                            <Search className={`size-4 shrink-0 ${blue ? 'text-white/80' : 'text-muted-foreground'}`} />
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

                {forum ? (
                    <form
                        onSubmit={search}
                        className="hidden w-full items-center gap-2 rounded-full bg-slate-100 px-4 py-2.5 sm:flex dark:bg-neutral-800"
                    >
                        <img src="/images/logo-mark.png" alt="" className="size-6 rounded-full" />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={t('Search anahnu.club')}
                            aria-label={t('Search anahnu.club')}
                            className="w-full min-w-0 bg-transparent text-[0.9375rem] outline-none"
                        />
                        <Search className="text-muted-foreground size-5 shrink-0" />
                    </form>
                ) : (
                    <nav className="hidden items-center justify-center gap-1 sm:flex">{tiles}</nav>
                )}

                <div className="flex items-center justify-end gap-1.5 sm:gap-2">
                    {forum && auth.user && (
                        <>
                            <MessengerMenu className={`${round} max-sm:hidden`}>
                                <MessageCircle className="size-6" />
                                <Badge count={counts?.messages ?? 0} className="-end-1 -top-1" />
                            </MessengerMenu>
                            <NotificationBell className={`${round} max-sm:hidden`}>
                                <Bell className="size-6" />
                                <Badge count={counts?.notifications ?? 0} className="-end-1 -top-1" />
                            </NotificationBell>
                        </>
                    )}
                    <LanguageSwitcher className="rounded-full bg-slate-100 px-3 py-2 text-slate-900 max-sm:px-2.5 dark:bg-neutral-800 dark:text-neutral-100 max-sm:[&>span:nth-child(2)]:hidden [&>svg]:hidden sm:[&>svg]:block" />
                    {auth.user ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="flex flex-col items-center gap-1 rounded-lg outline-none sm:w-14">
                                <Avatar className="size-9">
                                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                                <span
                                    className={`hidden w-full truncate text-center text-[0.6875rem] leading-tight sm:block ${blue ? 'text-white/85' : 'text-slate-600 dark:text-neutral-300'} ${forum ? 'sm:hidden' : ''}`}
                                >
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
            {/* On phones the tiles get a row of their own under the logo, so every name fits; Stream and Showcase put it at the bottom of the screen. */}
            {auth.user && (
                <nav
                    className={`flex items-start justify-around gap-0.5 border-t px-1 pb-1 sm:hidden ${blue ? 'border-white/15' : 'border-slate-100 dark:border-neutral-800'} ${flat ? 'fixed inset-x-0 bottom-0 z-40 border-slate-200 bg-white pt-1 pb-[max(0.25rem,env(safe-area-inset-bottom))] dark:bg-neutral-900' : ''}`}
                >
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
            className={`absolute flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[0.6875rem] font-bold text-white ${className}`}
        >
            {count > 99 ? '99+' : count}
        </span>
    );
}
