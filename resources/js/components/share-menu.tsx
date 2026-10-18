import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { Check, Link2, MessageCircle, Send, Share2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';

interface Network {
    name: string;
    badge: ReactNode;
    href: (url: string, text: string) => string;
}

const badge = (color: string, content: ReactNode) => (
    <span className={`flex size-7 shrink-0 items-center justify-center rounded-full text-[13px] font-bold text-white ${color}`}>{content}</span>
);

const networks: Network[] = [
    { name: 'Facebook', badge: badge('bg-[#1877f2]', 'f'), href: (u) => `https://www.facebook.com/sharer/sharer.php?u=${u}` },
    { name: 'Telegram', badge: badge('bg-[#229ed9]', <Send className="size-3.5" />), href: (u, t) => `https://t.me/share/url?url=${u}&text=${t}` },
    { name: 'WhatsApp', badge: badge('bg-[#25d366]', <MessageCircle className="size-3.5" />), href: (u, t) => `https://wa.me/?text=${t}%20${u}` },
    { name: 'X', badge: badge('bg-black', '𝕏'), href: (u, t) => `https://twitter.com/intent/tweet?url=${u}&text=${t}` },
    { name: 'VK', badge: badge('bg-[#0077ff]', 'vk'), href: (u, t) => `https://vk.com/share.php?url=${u}&title=${t}` },
];

/** "Share" button: a post's link to social networks, the phone's share sheet, or the clipboard. */
export function ShareMenu({ url, title, className }: { url: string; title: string; className?: string }) {
    const t = useT();
    const [copied, setCopied] = useState(false);
    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title.slice(0, 200));
    const canShareNatively = typeof navigator !== 'undefined' && 'share' in navigator;

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
                {copied ? <Check className="size-5" /> : <Share2 className="size-5" />} {copied ? t('Link copied') : t('Share')}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {networks.map((network) => (
                    <DropdownMenuItem key={network.name} asChild>
                        <a href={network.href(encodedUrl, encodedTitle)} target="_blank" rel="noreferrer" className="flex items-center gap-3">
                            {network.badge}
                            {network.name}
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
