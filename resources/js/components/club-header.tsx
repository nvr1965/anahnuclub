import { LanguageSwitcher } from '@/components/language-switcher';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { House, MessageCircle, Search, UserRound, Users } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

/** Facebook-style top bar: logo and search at the start, section tabs in the middle, account at the end. */
export function ClubHeader() {
    const t = useT();
    const getInitials = useInitials();
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const [q, setQ] = useState(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '');

    const counts = page.props.counts;
    const tabs = [
        { title: t('Feed'), href: route('dashboard'), match: '/feed', icon: House, badge: 0 },
        { title: t('Friends'), href: route('friends.index'), match: '/friends', icon: UserRound, badge: counts?.friend_requests ?? 0 },
        { title: t('Groups'), href: route('groups.index'), match: '/groups', icon: Users, badge: 0 },
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

    return (
        <header className="sticky top-0 z-40 bg-white shadow-sm dark:bg-neutral-900">
            <div className="from-header-from via-brand to-header-to h-1 bg-gradient-to-r" />
            <div className="grid h-14 grid-cols-[auto_1fr_auto] items-center gap-1.5 px-2 sm:gap-3 sm:px-3 lg:grid-cols-[1fr_auto_1fr]">
                <div className="flex items-center gap-1.5 sm:gap-2">
                    <Link href={auth.user ? route('dashboard') : route('home')} className="shrink-0" aria-label="anahnu.club">
                        <img src="/images/logo-mark.png" alt="" className="size-10 rounded-full" />
                    </Link>
                    {auth.user && (
                        <Link
                            href={route('search')}
                            aria-label={t('Search')}
                            className="flex size-10 items-center justify-center rounded-full bg-slate-100 sm:hidden dark:bg-neutral-800"
                        >
                            <Search className="size-5 text-slate-600" />
                        </Link>
                    )}
                    {auth.user && (
                        <form onSubmit={search} className="hidden items-center gap-2 rounded-full bg-slate-100 px-3 py-2 sm:flex dark:bg-neutral-800">
                            <Search className="text-muted-foreground size-4 shrink-0" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('Search anahnu.club')}
                                className="w-40 bg-transparent text-sm outline-none lg:w-52"
                            />
                        </form>
                    )}
                </div>

                <nav className="flex h-full items-stretch justify-center">
                    {auth.user &&
                        tabs.map((tab) => {
                            const active = page.url.startsWith(tab.match);
                            return (
                                <Link
                                    key={tab.match}
                                    href={tab.href}
                                    title={tab.title}
                                    className={`relative flex w-11 items-center justify-center sm:w-24 lg:w-28 ${active ? 'text-brand' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-800'} rounded-lg`}
                                >
                                    <tab.icon className="size-6" />
                                    <Badge count={tab.badge} className="start-1/2 top-1.5 ms-1" />
                                    {active && <span className="bg-brand absolute inset-x-1 -bottom-0 h-[3px] rounded-full" />}
                                </Link>
                            );
                        })}
                </nav>

                <div className="flex items-center justify-end gap-1.5 sm:gap-2">
                    <LanguageSwitcher
                        className={`rounded-full bg-slate-100 px-3 py-2 dark:bg-neutral-800 [&>svg]:hidden sm:[&>svg]:block ${auth.user ? 'max-sm:hidden' : ''}`}
                    />
                    {auth.user && (
                        <Link
                            href={route('messages.index')}
                            aria-label={t('Messages')}
                            className={`relative flex size-10 items-center justify-center rounded-full ${page.url.startsWith('/messages') ? 'bg-brand-soft text-brand' : 'bg-slate-100 dark:bg-neutral-800'}`}
                        >
                            <MessageCircle className="size-5" />
                            <Badge count={counts?.messages ?? 0} className="-end-1 -top-1" />
                        </Link>
                    )}
                    {auth.user ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="rounded-full outline-none">
                                <Avatar className="size-10">
                                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} className="object-cover" />
                                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(auth.user.name)}</AvatarFallback>
                                </Avatar>
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
