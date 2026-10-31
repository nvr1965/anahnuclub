import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';

export interface FriendCard extends Person {
    mutual?: number;
    mutual_people?: Person[];
}

/** "2 mutual friends" with their faces, under a name. */
export function MutualLine({ person, className = '' }: { person: FriendCard; className?: string }) {
    const t = useT();
    if (!person.mutual) {
        return <p className={`text-muted-foreground h-5 text-[13px] ${className}`} />;
    }

    return (
        <p className={`text-muted-foreground flex h-5 items-center gap-1.5 text-[13px] ${className}`}>
            <span className="flex -space-x-1.5 rtl:space-x-reverse">
                {(person.mutual_people ?? []).slice(0, 2).map((p) => (
                    <UserAvatar key={p.id} person={{ ...p, online: false }} className="size-4 ring-1 ring-white dark:ring-neutral-900" />
                ))}
            </span>
            <span className="truncate">{t(person.mutual === 1 ? '1 mutual friend' : ':count mutual friends', { count: person.mutual })}</span>
        </p>
    );
}
