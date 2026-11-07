import { CreateMenu } from '@/components/create-menu';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Ellipsis, Film, House, type LucideIcon, Menu, MessageCircle, PenLine, Plus, Search, UserRound, Users } from 'lucide-react';
import { useEffect } from 'react';

/**
 * The menu of the Stream and Showcase styles on wide screens, in place of the top bar.
 * wide: big icons with names and a "Write" button (Stream); icons: a narrow strip of icons, names from xl (Showcase).
 */
export function SideRail({ variant }: { variant: 'wide' | 'icons' }) {
    const t = useT();
    const page = usePage<SharedData>();
    const { auth, counts } = page.props;

    // The same badge refresh as the top bar, which is hidden while the rail shows.
    useEffect(() => {
        if (!auth.user) return;
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['counts'] });
        }, 30000);
        return () => clearInterval(timer);
    }, [auth.user?.id]);

    const items: { label: string; href: string; match: string; icon: LucideIcon; badge?: number }[] = [
        { label: t('Feed'), href: route('dashboard'), match: '/feed', icon: House },
        { label: t('Search'), href: route('search'), match: '/search', icon: Search },
        { label: t('Notifications'), href: route('notifications.index'), match: '/notifications', icon: Bell, badge: counts?.notifications },
        { label: t('Messages'), href: route('messages.index'), match: '/messages', icon: MessageCircle, badge: counts?.messages },
        { label: t('Friends'), href: route('friends.index'), match: '/friends', icon: UserRound, badge: counts?.friend_requests },
        { label: t('Groups'), href: route('groups.index'), match: '/groups', icon: Users },
        { label: t('Clips'), href: route('clips.index'), match: '/clips', icon: Film },
    ];
    if (auth.user)
        items.push({ label: t('My profile'), href: route('people.show', auth.user.id), match: `/people/${auth.user.id}`, icon: UserRound });

    const wide = variant === 'wide';
    // Icons only below xl in Showcase; Stream shows names from lg, where the rail appears.
    const name = wide ? '' : 'hidden xl:inline';
    const row = wide
        ? 'flex w-fit items-center gap-4 rounded-full py-2.5 ps-3 pe-5 text-xl hover:bg-slate-100 dark:hover:bg-neutral-900'
        : 'flex items-center gap-4 rounded-lg p-3 text-base hover:bg-slate-100 xl:w-full dark:hover:bg-neutral-900';

    const compose = () => {
        if (page.url.startsWith('/feed')) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => {
                const closed = document.querySelector<HTMLElement>('[data-composer-open]');
                if (closed) closed.click();
                else document.querySelector<HTMLElement>('form textarea')?.focus();
            }, 300);
        } else router.visit(route('dashboard'));
    };

    return (
        <nav className={`flex h-full flex-col ${wide ? 'gap-0.5 px-2 py-2' : 'gap-1 px-3 py-6'}`} aria-label={t('Menu')}>
            <Link
                href={route('dashboard')}
                className={wide ? 'mb-2 w-fit rounded-full p-2 hover:bg-slate-100 dark:hover:bg-neutral-900' : 'mb-6 p-2'}
                aria-label="anahnu.club"
            >
                {wide || <span className="hidden text-xl font-bold tracking-tight xl:inline">anahnu.club</span>}
                <img src="/images/logo-mark.png" alt="" className={wide ? 'size-9 rounded-full' : 'size-8 rounded-full xl:hidden'} />
            </Link>
            {items.map((item) => {
                const active = page.url.startsWith(item.match);
                return (
                    <Link key={item.href} href={item.href} className={`${row} ${active ? 'font-bold' : ''}`} title={item.label}>
                        <span className="relative shrink-0">
                            <item.icon className={wide ? 'size-7' : 'size-6'} strokeWidth={active ? 2.6 : 2} />
                            {(item.badge ?? 0) > 0 && (
                                <span className="bg-brand absolute -end-2 -top-1.5 flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full px-1 text-[0.6875rem] font-bold text-white">
                                    {(item.badge ?? 0) > 99 ? '99+' : item.badge}
                                </span>
                            )}
                        </span>
                        <span className={name}>{item.label}</span>
                    </Link>
                );
            })}
            {auth.user && (
                <CreateMenu className={`${row} outline-none`} align="start">
                    <Plus className={`shrink-0 ${wide ? 'size-7' : 'size-6'}`} />
                    <span className={name}>{t('Create')}</span>
                </CreateMenu>
            )}
            {wide && auth.user && (
                <button
                    type="button"
                    onClick={compose}
                    className="bg-brand hover:bg-brand-dark mt-4 flex h-[3.25rem] w-[90%] items-center justify-center rounded-full text-[1.0625rem] font-bold text-white"
                >
                    <PenLine className="size-5 xl:hidden" />
                    <span className="hidden xl:inline">{t('Write a post')}</span>
                </button>
            )}
            {auth.user && (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        className={`mt-auto outline-none ${wide ? 'flex w-full items-center gap-3 rounded-full p-3 text-start hover:bg-slate-100 dark:hover:bg-neutral-900' : row}`}
                    >
                        {wide ? (
                            <>
                                <Avatar className="size-10">
                                    <AvatarImage src={auth.user.avatar} alt="" className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                                <span className="hidden min-w-0 flex-1 xl:block">
                                    <span dir="auto" className="block truncate text-[0.9375rem] font-bold">
                                        {auth.user.name}
                                    </span>
                                </span>
                                <Ellipsis className="hidden size-5 xl:block" />
                            </>
                        ) : (
                            <>
                                <Menu className="size-6" />
                                <span className={name}>{t('More')}</span>
                            </>
                        )}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-60" align="start" side="top">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </nav>
    );
}
