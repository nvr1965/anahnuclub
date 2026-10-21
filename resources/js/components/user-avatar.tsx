import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { defaultImages } from '@/lib/default-images';
import { cn } from '@/lib/utils';

/** The default member picture, shown inside an avatar while a member has none of their own. */
export function DefaultAvatarFallback({ className = '' }: { className?: string }) {
    return (
        <AvatarFallback className={cn('bg-brand-soft', className)}>
            <img src={defaultImages.user} alt="" className="size-full object-cover" />
        </AvatarFallback>
    );
}

/** A member's round picture, with the default picture when they have none, and a green dot while they are online. */
export function UserAvatar({
    person,
    className = 'size-10',
    fallbackClassName = '',
}: {
    person: { name: string; avatar?: string | null; online?: boolean } | null;
    className?: string;
    fallbackClassName?: string;
}) {
    const avatar = (
        <Avatar className={className}>
            <AvatarImage src={person?.avatar ?? undefined} alt="" className="object-cover" />
            <DefaultAvatarFallback className={fallbackClassName} />
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
