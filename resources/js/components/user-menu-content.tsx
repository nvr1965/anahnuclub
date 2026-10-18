import { LanguageSwitcher } from '@/components/language-switcher';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useT } from '@/lib/i18n';
import { type User } from '@/types';
import { Link } from '@inertiajs/react';
import { CalendarDays, Clapperboard, Images, LogOut, Settings, Shield, UserRound } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const t = useT();
    const cleanup = useMobileNavigation();

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            {/* On phones the language choice lives here, the top bar has no room for it. */}
            <div className="px-2 py-1.5 sm:hidden" onKeyDown={(e) => e.stopPropagation()}>
                <LanguageSwitcher className="w-full rounded-md bg-slate-100 px-3 py-2 dark:bg-neutral-800" />
            </div>
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link className="block w-full" href={route('people.show', user.id)} as="button" onClick={cleanup}>
                        <UserRound className="me-2" />
                        {t('My profile')}
                    </Link>
                </DropdownMenuItem>
                {/* The side menu is hidden on phones, so its sections are reachable from here. */}
                {[
                    { href: route('people.show', { user: user.id, tab: 'photos' }), icon: Images, label: t('Photos') },
                    { href: route('videos.index'), icon: Clapperboard, label: t('Videos') },
                    { href: route('events.index'), icon: CalendarDays, label: t('Events') },
                ].map((item) => (
                    <DropdownMenuItem key={item.href} asChild className="lg:hidden">
                        <Link className="block w-full" href={item.href} as="button" onClick={cleanup}>
                            <item.icon className="me-2" />
                            {item.label}
                        </Link>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuItem asChild>
                    <Link className="block w-full" href={route('profile.edit')} as="button" prefetch onClick={cleanup}>
                        <Settings className="me-2" />
                        {t('Settings')}
                    </Link>
                </DropdownMenuItem>
                {Boolean(user.is_admin) && (
                    <DropdownMenuItem asChild>
                        <Link className="block w-full" href={route('admin.index')} as="button" onClick={cleanup}>
                            <Shield className="me-2" />
                            {t('Administration')}
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link className="block w-full" method="post" href={route('logout')} as="button" onClick={cleanup}>
                    <LogOut className="me-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
