import { ShareMenu } from '@/components/share-menu';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';

const style =
    'bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap text-white outline-none';

/** "Invite friends": the club's link with a short invitation, sent through a messenger, Facebook or mail. */
export function InviteButton({ className = style }: { className?: string }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    // The language goes along, so the preview and the page open in it.
    const url = `${route('home')}?lang=${locale}`;
    const text = t('Join me on anahnu.club, the best social network without censorship!');

    return <ShareMenu url={url} title={text} label={t('Invite friends')} icon={<UserPlus className="size-4" />} className={className} />;
}
