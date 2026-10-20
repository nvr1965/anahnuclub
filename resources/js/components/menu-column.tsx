import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type SharedData, type Sidebar } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, Clapperboard, Contact, House, Images, MessageCircle, Settings, Shield, UserRound, Users } from 'lucide-react';

/** Left column like Facebook's: you, the main sections, then your groups. */
export function MenuColumn({ groups }: { groups?: Sidebar['groups'] }) {
    const t = useT();
    const getInitials = useInitials();
    const { auth } = usePage<SharedData>().props;
    const row = 'flex items-center gap-3 rounded-lg px-2 py-2 text-[15px] font-medium hover:bg-black/5 dark:hover:bg-white/10';

    return (
        <nav className="space-y-1">
            {auth.user && (
                <Link href={route('people.show', auth.user.id)} className={row}>
                    <Avatar className="size-9">
                        <AvatarImage src={auth.user.avatar} alt="" className="object-cover" />
                        <AvatarFallback className="bg-brand-soft text-brand text-sm">{getInitials(auth.user.name)}</AvatarFallback>
                    </Avatar>
                    {auth.user.name}
                </Link>
            )}
            <Link href={route('dashboard')} className={row}>
                <span className="bg-brand flex size-9 items-center justify-center rounded-full text-white">
                    <House className="size-5" />
                </span>
                {t('Feed')}
            </Link>
            <Link href={route('friends.index')} className={row}>
                <span className="flex size-9 items-center justify-center rounded-full bg-sky-500 text-white">
                    <UserRound className="size-5" />
                </span>
                {t('Friends')}
            </Link>
            {auth.user && (
                <Link href={route('people.index')} className={row}>
                    <span className="flex size-9 items-center justify-center rounded-full bg-amber-500 text-white">
                        <Contact className="size-5" />
                    </span>
                    {t('All members')}
                </Link>
            )}
            <Link href={route('messages.index')} className={row}>
                <span className="flex size-9 items-center justify-center rounded-full bg-violet-500 text-white">
                    <MessageCircle className="size-5" />
                </span>
                {t('Messages')}
            </Link>
            <Link href={route('groups.index')} className={row}>
                <span className="bg-header-from flex size-9 items-center justify-center rounded-full text-white">
                    <Users className="size-5" />
                </span>
                {t('Groups')}
            </Link>
            {auth.user && (
                <Link href={route('people.show', { user: auth.user.id, tab: 'photos' })} className={row}>
                    <span className="flex size-9 items-center justify-center rounded-full bg-green-500 text-white">
                        <Images className="size-5" />
                    </span>
                    {t('Photos')}
                </Link>
            )}
            <Link href={route('videos.index')} className={row}>
                <span className="flex size-9 items-center justify-center rounded-full bg-rose-500 text-white">
                    <Clapperboard className="size-5" />
                </span>
                {t('Videos')}
            </Link>
            <Link href={route('events.index')} className={row}>
                <span className="flex size-9 items-center justify-center rounded-full bg-red-500 text-white">
                    <CalendarDays className="size-5" />
                </span>
                {t('Events')}
            </Link>
            <Link href={route('profile.edit')} className={row}>
                <span className="flex size-9 items-center justify-center rounded-full bg-slate-300 text-slate-700 dark:bg-neutral-700 dark:text-neutral-200">
                    <Settings className="size-5" />
                </span>
                {t('Settings')}
            </Link>
            {Boolean(auth.user?.is_admin) && (
                <Link href={route('admin.index')} className={row}>
                    <span className="bg-ink flex size-9 items-center justify-center rounded-full text-white">
                        <Shield className="size-5" />
                    </span>
                    {t('Administration')}
                </Link>
            )}

            {groups && groups.length > 0 && (
                <>
                    <div className="mx-2 my-3 border-t border-slate-300 dark:border-neutral-700" />
                    <h3 className="text-muted-foreground px-2 pb-1 text-sm font-semibold">{t('Your groups')}</h3>
                    {groups.map((group) => (
                        <Link key={group.slug} href={route('groups.show', group.slug)} className={row}>
                            <span className="from-brand to-brand-dark flex size-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-sm text-white">
                                {group.name.trim().charAt(0).toUpperCase()}
                            </span>
                            <span dir="auto" className="truncate">
                                {group.name}
                            </span>
                        </Link>
                    ))}
                </>
            )}
        </nav>
    );
}
