import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';

/** A member's round picture, with their initials when they have none, and a green dot while they are online. */
export function UserAvatar({
    person,
    className = 'size-10',
    fallbackClassName = '',
}: {
    person: { name: string; avatar?: string | null; online?: boolean } | null;
    className?: string;
    fallbackClassName?: string;
}) {
    const getInitials = useInitials();

    const avatar = (
        <Avatar className={className}>
            <AvatarImage src={person?.avatar ?? undefined} alt="" className="object-cover" />
            <AvatarFallback className={`bg-brand-soft text-brand ${fallbackClassName}`}>{getInitials(person?.name ?? '?')}</AvatarFallback>
        </Avatar>
    );
    if (!person?.online) return avatar;

    // A green dot on the picture of members who are on the site now.
    return (
        <span className="relative inline-flex shrink-0">
            {avatar}
            <span className="absolute end-[4%] bottom-[4%] size-[22%] max-h-[18px] min-h-2.5 max-w-[18px] min-w-2.5 rounded-full bg-green-500 ring-2 ring-white dark:ring-neutral-900" />
        </span>
    );
}
