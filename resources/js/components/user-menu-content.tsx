import { useInstallOffered } from '@/components/install-app';
import { LanguageSubmenu } from '@/components/language-switcher';
import { SkinSubmenu } from '@/components/skin-picker';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useT } from '@/lib/i18n';
import { installApp } from '@/lib/install-app';
import { type User } from '@/types';
import { Link } from '@inertiajs/react';
import { CalendarDays, Clapperboard, Film, Images, LogOut, Settings, Shield, Smartphone, UserRound } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
    /** phones: only where the top bar has no room for the language switcher; always: the side rail of Stream and Showcase, which has no top bar. */
    languages?: 'phones' | 'always';
}

export function UserMenuContent({ user, languages = 'phones' }: UserMenuContentProps) {
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
            {/* On phones the language choice lives here, the top bar has no room for it; the side rail has no top bar at all. */}
            <div className={languages === 'phones' ? 'sm:hidden' : ''}>
                <LanguageSubmenu />
            </div>
            <SkinSubmenu />
            <DropdownMenuSeparator />
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
                    { href: route('clips.index'), icon: Film, label: t('Clips') },
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
                <InstallAppItem />
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

/** Puts the club on the home screen: the browser's own prompt, or the steps for this phone or computer. */
function InstallAppItem() {
    const t = useT();
    if (!useInstallOffered()) return null;

    return (
        <DropdownMenuItem onSelect={installApp}>
            <Smartphone className="me-2" />
            {t('Install the app')}
        </DropdownMenuItem>
    );
}
