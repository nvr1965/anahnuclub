import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Check, Link2, Mail, MessageCircle, MessageSquare, Phone, Send, Share2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';

interface Network {
    name: string;
    badge: ReactNode;
    href: (url: string, text: string) => string;
}

const badge = (color: string, content: ReactNode) => (
    <span className={`flex size-7 shrink-0 items-center justify-center rounded-full text-[13px] font-bold text-white ${color}`}>{content}</span>
);

// WhatsApp, Telegram and Viber open the app (or its site) with the contact list to pick from.
const networks: Network[] = [
    { name: 'WhatsApp', badge: badge('bg-[#25d366]', <MessageCircle className="size-3.5" />), href: (u, t) => `https://wa.me/?text=${t}%20${u}` },
    { name: 'Telegram', badge: badge('bg-[#229ed9]', <Send className="size-3.5" />), href: (u, t) => `https://t.me/share/url?url=${u}&text=${t}` },
    { name: 'Facebook', badge: badge('bg-[#1877f2]', 'f'), href: (u) => `https://www.facebook.com/sharer/sharer.php?u=${u}` },
    { name: 'Viber', badge: badge('bg-[#7360f2]', <Phone className="size-3.5" />), href: (u, t) => `viber://forward?text=${t}%20${u}` },
    { name: 'X', badge: badge('bg-black', '𝕏'), href: (u, t) => `https://twitter.com/intent/tweet?url=${u}&text=${t}` },
    { name: 'VK', badge: badge('bg-[#0077ff]', 'vk'), href: (u, t) => `https://vk.com/share.php?url=${u}&title=${t}` },
    { name: 'Email', badge: badge('bg-slate-600', <Mail className="size-3.5" />), href: (u, t) => `mailto:?subject=${t}&body=${t}%0A%0A${u}` },
];

/** Text messages only make sense on a phone. */
const sms: Network = {
    name: 'SMS',
    badge: badge('bg-emerald-600', <MessageSquare className="size-3.5" />),
    href: (u, t) => `sms:?&body=${t}%20${u}`,
};

/** The link in the sharer's language (?lang=he): the card Facebook or WhatsApp shows, and the page it opens, are in it. */
function inLanguage(url: string, locale: string): string {
    try {
        const link = new URL(url, window.location.origin);
        if (link.origin !== window.location.origin || link.searchParams.has('lang')) return url;
        link.searchParams.set('lang', locale);
        return link.toString();
    } catch {
        return url;
    }
}

/** "Share" button: a link to social networks and messengers, the phone's share sheet, or the clipboard. */
export function ShareMenu({
    url: address,
    title,
    className,
    label,
    icon,
}: {
    url: string;
    title: string;
    className?: string;
    /** Instead of "Share", e.g. "Invite friends". */
    label?: string;
    icon?: ReactNode;
}) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const url = inLanguage(address, locale);
    const [copied, setCopied] = useState(false);
    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title.slice(0, 200));
    const canShareNatively = typeof navigator !== 'undefined' && 'share' in navigator;
    const phone = typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            window.prompt(t('Copy link'), url);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className={className}>
                {copied ? <Check className="size-5" /> : (icon ?? <Share2 className="size-5" />)} {copied ? t('Link copied') : (label ?? t('Share'))}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {(phone ? [...networks, sms] : networks).map((network) => (
                    <DropdownMenuItem key={network.name} asChild>
                        <a href={network.href(encodedUrl, encodedTitle)} target="_blank" rel="noreferrer" className="flex items-center gap-3">
                            {network.badge}
                            {network.name === 'Email' ? t('Email') : network.name}
                        </a>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={copy} className="flex items-center gap-3">
                    {badge('bg-slate-500', <Link2 className="size-3.5" />)}
                    {t('Copy link')}
                </DropdownMenuItem>
                {canShareNatively && (
                    <DropdownMenuItem onSelect={() => navigator.share({ url, title }).catch(() => undefined)} className="flex items-center gap-3">
                        {badge('bg-slate-400', <Share2 className="size-3.5" />)}
                        {t('More…')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
