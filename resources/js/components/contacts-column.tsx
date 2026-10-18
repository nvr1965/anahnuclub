import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type Sidebar } from '@/types';
import { Link } from '@inertiajs/react';

/** Right column like Facebook's contacts: recently active members, then photos. */
export function ContactsColumn({ sidebar }: { sidebar: Sidebar }) {
    const t = useT();
    const getInitials = useInitials();

    return (
        <div className="space-y-4">
            {sidebar.photos.length > 0 && (
                <section>
                    <h3 className="text-muted-foreground px-2 pb-2 text-sm font-semibold">{t('Photos')}</h3>
                    <div className="grid grid-cols-3 gap-1.5 px-2">
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
                </section>
            )}
            <div className="mx-2 border-t border-slate-300 dark:border-neutral-700" />
            <section>
                <h3 className="text-muted-foreground flex justify-between px-2 pb-1 text-sm font-semibold">
                    <span>{t('Members')}</span>
                    <span className="font-normal">{sidebar.members_count}</span>
                </h3>
                {sidebar.members.map((member) => (
                    <Link key={member.id} href={route('people.show', member.id)} className="flex items-center gap-3 rounded-lg px-2 py-1.5 text-[15px] hover:bg-black/5 dark:hover:bg-white/10">
                        <Avatar className="size-9">
                            <AvatarImage src={member.avatar ?? undefined} alt="" className="object-cover" />
                            <AvatarFallback className="bg-brand-soft text-brand text-sm">{getInitials(member.name)}</AvatarFallback>
                        </Avatar>
                        <span dir="auto" className="truncate">
                            {member.name}
                        </span>
                    </Link>
                ))}
            </section>
        </div>
    );
}
