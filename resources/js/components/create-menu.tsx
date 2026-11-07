import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { type CreateKind, startCreating } from '@/lib/create';
import { useT } from '@/lib/i18n';
import { CalendarPlus, CirclePlus, Film, type LucideIcon, SquarePen, Users, Video } from 'lucide-react';
import { type ReactNode } from 'react';

/** The "+" of the top bar, as on Facebook: everything a member can make, in one place. */
export function CreateMenu({
    className,
    children,
    align = 'end',
    side,
}: {
    className: string;
    children: ReactNode;
    align?: 'start' | 'end';
    side?: 'top' | 'bottom';
}) {
    const t = useT();
    const items: { kind: CreateKind; label: string; icon: LucideIcon }[] = [
        { kind: 'post', label: t('Post'), icon: SquarePen },
        { kind: 'moment', label: t('Moment'), icon: CirclePlus },
        { kind: 'clip', label: t('Clip'), icon: Film },
        { kind: 'video', label: t('Video'), icon: Video },
        { kind: 'event', label: t('Event'), icon: CalendarPlus },
        { kind: 'group', label: t('Group'), icon: Users },
    ];

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger className={className} aria-label={t('Create')} title={t('Create')}>
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} side={side} className="w-60 rounded-2xl p-1.5">
                {items.map((item) => (
                    <DropdownMenuItem
                        key={item.kind}
                        onSelect={() => startCreating(item.kind)}
                        className="gap-3 rounded-xl px-3 py-2.5 text-[0.9375rem] font-medium"
                    >
                        <item.icon className="size-5" />
                        {item.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
