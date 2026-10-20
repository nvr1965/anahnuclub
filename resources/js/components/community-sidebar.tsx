import { ClubCard } from '@/components/club-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type Sidebar } from '@/types';
import { Link } from '@inertiajs/react';

export function CommunitySidebar({ sidebar }: { sidebar: Sidebar }) {
    const t = useT();
    const getInitials = useInitials();

    return (
        <>
            <ClubCard title={t('Members')}>
                <div className="grid grid-cols-6 gap-2">
                    {sidebar.members.map((member) => (
                        <Link key={member.id} href={route('people.show', member.id)} title={member.name}>
                            <Avatar className="size-10 ring-2 ring-white dark:ring-neutral-900">
                                <AvatarImage src={member.avatar ?? undefined} alt={member.name} className="object-cover" />
                                <AvatarFallback className="bg-brand-soft text-brand text-xs">{getInitials(member.name)}</AvatarFallback>
                            </Avatar>
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
                    <ul className="space-y-2 text-sm">
                        {sidebar.groups.map((group) => (
                            <li key={group.slug} className="flex items-center justify-between gap-2">
                                <Link href={route('groups.show', group.slug)} className="hover:text-brand truncate">
                                    {group.name}
                                </Link>
                                <span className="text-muted-foreground shrink-0 text-xs">{group.posts_count}</span>
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
