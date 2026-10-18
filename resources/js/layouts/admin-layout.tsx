import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, Gauge, Languages, Users, UsersRound } from 'lucide-react';
import { type ReactNode } from 'react';

/** Admin pages: the club frame with the admin sections as the start-side menu. */
export default function AdminLayout({ title, children, actions }: { title: string; children: ReactNode; actions?: ReactNode }) {
    const t = useT();
    const { url } = usePage();
    const items = [
        { title: t('Overview'), href: route('admin.index'), match: (u: string) => u === '/admin' || u.startsWith('/admin?'), icon: Gauge },
        { title: t('Users'), href: route('admin.users.index'), match: (u: string) => u.startsWith('/admin/users'), icon: Users },
        { title: t('Posts'), href: route('admin.posts.index'), match: (u: string) => u.startsWith('/admin/posts'), icon: FileText },
        { title: t('Groups'), href: route('admin.groups.index'), match: (u: string) => u.startsWith('/admin/groups'), icon: UsersRound },
        { title: t('Languages'), href: route('admin.languages.index'), match: (u: string) => u.startsWith('/admin/languages'), icon: Languages },
    ];

    const menu = (
        <nav className="space-y-1">
            <div className="text-muted-foreground px-3 pb-2 text-xs font-semibold tracking-wide uppercase">{t('Administration')}</div>
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-[15px] ${item.match(url) ? 'bg-brand-soft text-brand font-semibold' : 'hover:bg-black/5 dark:hover:bg-white/5'}`}
                >
                    <item.icon className="size-5" />
                    {item.title}
                </Link>
            ))}
        </nav>
    );

    return (
        <ClubLayout left={menu}>
            <Head title={`${title} · ${t('Administration')}`} />
            {/* On small screens the side menu is hidden, so the sections show as a scrollable row. */}
            <div className="mb-3 flex gap-2 overflow-x-auto lg:hidden">
                {items.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`shrink-0 rounded-full px-3 py-1.5 text-sm ${item.match(url) ? 'bg-brand text-white' : 'bg-white dark:bg-neutral-900'}`}
                    >
                        {item.title}
                    </Link>
                ))}
            </div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">{title}</h1>
                {actions}
            </div>
            {children}
        </ClubLayout>
    );
}
