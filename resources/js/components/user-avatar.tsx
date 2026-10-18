import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';

/** A member's round picture, with their initials when they have none. */
export function UserAvatar({
    person,
    className = 'size-10',
    fallbackClassName = '',
}: {
    person: { name: string; avatar?: string | null } | null;
    className?: string;
    fallbackClassName?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar className={className}>
            <AvatarImage src={person?.avatar ?? undefined} alt="" className="object-cover" />
            <AvatarFallback className={`bg-brand-soft text-brand ${fallbackClassName}`}>{getInitials(person?.name ?? '?')}</AvatarFallback>
        </Avatar>
    );
}
