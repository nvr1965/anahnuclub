import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useT } from '@/lib/i18n';
import { Newspaper, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const t = useT();
    const { dir } = usePage<SharedData>().props;
    const mainNavItems: NavItem[] = [
        { title: t('Feed'), url: route('dashboard', undefined, false), icon: Newspaper },
        { title: t('Groups'), url: route('groups.index', undefined, false), icon: Users },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" side={dir === 'rtl' ? 'right' : 'left'}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <LanguageSwitcher className="px-2" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
