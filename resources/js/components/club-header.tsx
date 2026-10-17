import { LanguageSwitcher } from '@/components/language-switcher';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { House, Search, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

/** Facebook-style top bar: logo and search at the start, section tabs in the middle, account at the end. */
export function ClubHeader() {
    const t = useT();
    const getInitials = useInitials();
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const [q, setQ] = useState(() => new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '');

    const tabs = [
        { title: t('Feed'), href: route('dashboard'), match: '/feed', icon: House },
        { title: t('Groups'), href: route('groups.index'), match: '/groups', icon: Users },
    ];

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        if (q.trim()) router.get(route('search'), { q: q.trim() });
    };

    return (
        <header className="sticky top-0 z-40 bg-white shadow-sm dark:bg-neutral-900">
            <div className="from-header-from via-brand to-header-to h-1 bg-gradient-to-r" />
            <div className="grid h-14 grid-cols-[auto_1fr_auto] items-center gap-3 px-3 lg:grid-cols-[1fr_auto_1fr]">
                <div className="flex items-center gap-2">
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
                        <form onSubmit={search} className="hidden sm:flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 dark:bg-neutral-800">
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
                                    className={`relative flex w-12 items-center justify-center sm:w-24 lg:w-28 ${active ? 'text-brand' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-neutral-800'} rounded-lg`}
                                >
                                    <tab.icon className="size-6" />
                                    {active && <span className="bg-brand absolute inset-x-1 -bottom-0 h-[3px] rounded-full" />}
                                </Link>
                            );
                        })}
                </nav>

                <div className="flex items-center justify-end gap-2">
                    <LanguageSwitcher className="rounded-full bg-slate-100 px-3 py-2 dark:bg-neutral-800 [&>svg]:hidden sm:[&>svg]:block" />
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
