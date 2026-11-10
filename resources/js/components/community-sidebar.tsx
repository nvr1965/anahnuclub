import { ClubCard } from '@/components/club-card';
import { UserAvatar } from '@/components/user-avatar';
import { defaultImages } from '@/lib/default-images';
import { useT } from '@/lib/i18n';
import { type Sidebar } from '@/types';
import { Link } from '@inertiajs/react';

export function CommunitySidebar({ sidebar }: { sidebar: Sidebar }) {
    const t = useT();

    return (
        <>
            <ClubCard title={t('Members')}>
                <div className="grid grid-cols-6 gap-2">
                    {sidebar.members.map((member) => (
                        <Link key={member.id} href={route('people.show', member.id)} title={member.name}>
                            <UserAvatar person={member} className="size-10 ring-2 ring-white dark:ring-neutral-900" fallbackClassName="text-xs" />
                        </Link>
                    ))}
                </div>
                <div className="mt-3 flex items-center justify-between gap-2 text-xs">
                    <span className="text-muted-foreground">{t(':count members', { count: sidebar.members_count })}</span>
                    <Link href={route('people.index')} className="text-brand font-semibold hover:underline">
                        {t('Show all')}
                    </Link>
                </div>
            </ClubCard>

            {sidebar.groups.length > 0 && (
                <ClubCard title={t('Groups')}>
                    <ul className="space-y-1 text-sm">
                        {sidebar.groups.map((group) => (
                            <li key={group.slug}>
                                <Link
                                    href={route('groups.show', group.slug)}
                                    title={group.name}
                                    className="-mx-2 flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-black/5 dark:hover:bg-white/10"
                                >
                                    <img
                                        src={group.avatar || defaultImages.group}
                                        alt=""
                                        loading="lazy"
                                        onError={(e) => {
                                            if (!e.currentTarget.src.endsWith(defaultImages.group)) e.currentTarget.src = defaultImages.group;
                                        }}
                                        className="size-8 shrink-0 rounded-lg bg-slate-200 object-cover dark:bg-neutral-700"
                                    />
                                    <span dir="auto" className="min-w-0 truncate font-medium">
                                        {group.name}
                                    </span>
                                    <span className="text-muted-foreground ms-auto shrink-0 text-xs">{group.posts_count}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </ClubCard>
            )}

            {sidebar.photos.length > 0 && (
                <ClubCard title={t('Photos')}>
                    <div className="grid grid-cols-3 gap-2">
                        {sidebar.photos.map((photo) => (
                            <a key={photo.id} href={photo.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg">
                                <img
                                    src={photo.thumb}
                                    alt=""
                                    loading="lazy"
                                    onError={(e) => ((e.currentTarget.parentElement as HTMLElement).style.display = 'none')}
                                    className="aspect-square w-full object-cover transition hover:scale-105"
                                />
                            </a>
                        ))}
                    </div>
                </ClubCard>
            )}
        </>
    );
}
