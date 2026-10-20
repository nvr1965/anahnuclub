import { ShareMenu } from '@/components/share-menu';
import { useT } from '@/lib/i18n';
import { UserPlus } from 'lucide-react';

const style =
    'bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap text-white outline-none';

/** "Invite friends": the club's (or a group's) link with a short invitation, sent through a messenger, Facebook or mail. */
export function InviteButton({ group, className = style }: { group?: { name: string; slug: string }; className?: string }) {
    const t = useT();
    const url = group ? route('groups.show', group.slug) : route('home');
    const text = group ? t('Join the group “:name” on anahnu.club', { name: group.name }) : t('Join me on anahnu.club, the community of our people!');

    return <ShareMenu url={url} title={text} label={t('Invite friends')} icon={<UserPlus className="size-4" />} className={className} />;
}
