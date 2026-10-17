import { LanguageSwitcher } from '@/components/language-switcher';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Newspaper, Users } from 'lucide-react';

/** The indigo bar from the old anahnu.club, with navigation added. */
export function ClubHeader() {
    const t = useT();
    const getInitials = useInitials();
    const page = usePage<SharedData>();
    const { auth } = page.props;

    const links = [
        { title: t('Feed'), href: route('dashboard'), match: '/feed', icon: Newspaper },
        { title: t('Groups'), href: route('groups.index'), match: '/groups', icon: Users },
    ];

    return (
        <header className="from-header-from to-header-to sticky top-0 z-40 bg-gradient-to-r text-white shadow-md">
            <div className="mx-auto flex h-14 max-w-6xl items-center gap-4 px-4">
                <Link href={auth.user ? route('dashboard') : route('home')} className="flex shrink-0 items-center gap-2">
                    <img src="/images/logo-mark.png" alt="" className="size-8 rounded-md" />
                    <span className="hidden text-sm font-semibold tracking-[0.2em] sm:inline">anahnu.club</span>
                </Link>

                {auth.user && (
                    <nav className="flex items-center gap-1">
                        {links.map((link) => {
                            const active = page.url.startsWith(link.match);
                            return (
                                <Link
                                    key={link.match}
                                    href={link.href}
                                    className={`flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition ${active ? 'bg-white/20 font-medium' : 'hover:bg-white/10'}`}
                                >
                                    <link.icon className="size-4" />
                                    <span className="hidden sm:inline">{link.title}</span>
                                </Link>
                            );
                        })}
                    </nav>
                )}

                <div className="ms-auto flex items-center gap-3">
                    <LanguageSwitcher className="[&_select]:text-white [&_option]:text-black" />
                    {auth.user ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="rounded-full ring-2 ring-white/40 outline-none">
                                <Avatar className="size-8">
                                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                    <AvatarFallback className="bg-white/20 text-xs text-white">{getInitials(auth.user.name)}</AvatarFallback>
                                </Avatar>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        <Link href={route('login')} className="rounded-full bg-white/15 px-4 py-1.5 text-sm hover:bg-white/25">
                            {t('Log in')}
                        </Link>
                    )}
                </div>
            </div>
        </header>
    );
}
