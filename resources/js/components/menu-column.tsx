import { useInstallOffered } from '@/components/install-app';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { defaultImages } from '@/lib/default-images';
import { useT } from '@/lib/i18n';
import { installApp } from '@/lib/install-app';
import { type SharedData, type Sidebar } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, Clapperboard, Contact, Film, House, Images, MessageCircle, Settings, Smartphone, UserRound, Users } from 'lucide-react';

/** Left column like Facebook's: you, the main sections, then your groups. */
export function MenuColumn({ groups }: { groups?: Sidebar['groups'] }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const installOffered = useInstallOffered();
    const row = 'flex items-center gap-3 rounded-lg px-2 py-2 text-[0.9375rem] font-medium hover:bg-black/5 dark:hover:bg-white/10';

    return (
        <nav className="space-y-1">
            {auth.user && (
                <Link href={route('people.show', auth.user.id)} className={row}>
                    <Avatar className="size-9">
                        <AvatarImage src={auth.user.avatar} alt="" className="object-cover" />
                        <DefaultAvatarFallback />
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
            {auth.user && (
                <Link href={route('clips.index')} className={row}>
                    <span className="flex size-9 items-center justify-center rounded-full bg-gradient-to-tr from-amber-400 via-rose-500 to-fuchsia-600 text-white">
                        <Film className="size-5" />
                    </span>
                    {t('Clips')}
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
            {installOffered && (
                <button type="button" onClick={installApp} className={`${row} w-full text-start`}>
                    <span className="bg-brand-soft text-brand dark:bg-brand/20 flex size-9 items-center justify-center rounded-full">
                        <Smartphone className="size-5" />
                    </span>
                    {t('Install the app')}
                </button>
            )}

            {groups && groups.length > 0 && (
                <>
                    <div className="mx-2 my-3 border-t border-slate-300 dark:border-neutral-700" />
                    <div className="flex items-baseline justify-between gap-2 px-2 pb-1">
                        <h3 className="text-muted-foreground text-sm font-semibold">{t('Your groups')}</h3>
                        <Link href={route('groups.index')} className="text-brand shrink-0 text-sm hover:underline">
                            {t('See all')}
                        </Link>
                    </div>
                    {groups.map((group) => (
                        <Link key={group.slug} href={route('groups.show', group.slug)} title={group.name} className={row}>
                            <img
                                src={group.avatar || defaultImages.group}
                                alt=""
                                loading="lazy"
                                onError={(e) => {
                                    if (!e.currentTarget.src.endsWith(defaultImages.group)) e.currentTarget.src = defaultImages.group;
                                }}
                                className="size-9 shrink-0 rounded-lg bg-slate-200 object-cover dark:bg-neutral-700"
                            />
                            <span dir="auto" className="min-w-0 truncate">
                                {group.name}
                            </span>
                        </Link>
                    ))}
                </>
            )}
        </nav>
    );
}
