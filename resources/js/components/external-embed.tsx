import { useT } from '@/lib/i18n';
import { type EmbedCard } from '@/types';
import { ExternalLink, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** Reads the height an embedded page reports: Telegram, X, Instagram and our own posts tell it. */
function reportedHeight(data: unknown): number | null {
    let message = data;
    if (typeof message === 'string') {
        try {
            message = JSON.parse(message);
        } catch {
            return null;
        }
    }
    if (!message || typeof message !== 'object') return null;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const m = message as Record<string, any>;
    const height =
        (m.event === 'resize' && m.height) ||
        (m.type === 'MEASURE' && m.details?.height) ||
        (m.type === 'anahnu-embed' && m.height) ||
        m['twttr.embed']?.params?.[0]?.height;
    return typeof height === 'number' && height > 50 && height < 5000 ? Math.ceil(height) : null;
}

const SITES: Record<string, string> = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    telegram: 'Telegram',
    tiktok: 'TikTok',
    x: 'X',
    youtube: 'YouTube',
    vimeo: 'Vimeo',
};
const ALWAYS = 'embeds-always';

function alwaysShown() {
    try {
        return localStorage.getItem(ALWAYS) === '1';
    } catch {
        return false;
    }
}

/**
 * A post of another site played inside a post here. It loads only after a click (or always, when the visitor
 * chose so): the other site receives the visitor's address and may set its own cookies. The address comes from the server, which
 * builds it only for known sites (app/Support/Embed.php); the frame cannot reach this page.
 */
export function ExternalEmbed({ embed, onRemove }: { embed: EmbedCard; onRemove?: () => void }) {
    const t = useT();
    const frame = useRef<HTMLIFrameElement>(null);
    const [height, setHeight] = useState(embed.height);
    // The author previewing their own post has chosen the link: no need to ask.
    const [shown, setShown] = useState(() => Boolean(onRemove) || alwaysShown());
    const site = SITES[embed.provider] ?? new URL(embed.url).hostname;

    useEffect(() => {
        setHeight(embed.height);
        if (embed.height === null) return;
        const listen = (e: MessageEvent) => {
            if (e.source !== frame.current?.contentWindow) return;
            const reported = reportedHeight(e.data);
            if (reported) setHeight(reported);
        };
        window.addEventListener('message', listen);
        return () => window.removeEventListener('message', listen);
    }, [embed]);

    if (!shown) {
        const always = () => {
            try {
                localStorage.setItem(ALWAYS, '1');
            } catch {
                // Without storage it is shown this time only.
            }
            setShown(true);
        };
        return (
            <div className="flex flex-col items-center gap-3 bg-slate-100 px-4 py-6 text-center dark:bg-neutral-800">
                <p className="max-w-md text-sm">
                    {t(':site content. It loads from :site, which will receive your IP address and may set its own cookies.', { site })}
                </p>
                <div className="flex flex-wrap justify-center gap-2">
                    <button type="button" onClick={() => setShown(true)} className="bg-brand rounded-md px-4 py-2 text-sm font-semibold text-white">
                        {t('Show')}
                    </button>
                    <button
                        type="button"
                        onClick={always}
                        className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium dark:border-neutral-700 dark:bg-neutral-900"
                    >
                        {t('Always show')}
                    </button>
                </div>
                <a
                    href={embed.url}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="text-brand inline-flex items-center gap-1 text-sm hover:underline"
                >
                    {t('Open on :site', { site })} <ExternalLink className="size-3.5" aria-hidden="true" />
                </a>
            </div>
        );
    }

    return (
        <div className={`relative flex justify-center ${embed.height === null ? 'bg-black' : ''}`}>
            <iframe
                ref={frame}
                src={embed.src}
                title={embed.url}
                loading="lazy"
                scrolling="no"
                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen"
                allowFullScreen
                referrerPolicy="strict-origin-when-cross-origin"
                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation"
                className={`block w-full border-0 ${embed.height === null ? 'aspect-video' : 'max-w-[550px]'}`}
                style={height === null ? undefined : { height }}
            />
            {onRemove && (
                <button
                    type="button"
                    aria-label={t('Remove')}
                    onClick={onRemove}
                    className="absolute end-2 top-2 rounded-full bg-black/60 p-1.5 text-white hover:bg-black/80"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
