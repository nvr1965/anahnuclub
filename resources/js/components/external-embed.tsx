import { useT } from '@/lib/i18n';
import { type EmbedCard } from '@/types';
import { X } from 'lucide-react';
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

/**
 * A post of another site played inside a post here. The address comes from the server, which
 * builds it only for known sites (app/Support/Embed.php); the frame cannot reach this page.
 */
export function ExternalEmbed({ embed, onRemove }: { embed: EmbedCard; onRemove?: () => void }) {
    const t = useT();
    const frame = useRef<HTMLIFrameElement>(null);
    const [height, setHeight] = useState(embed.height);

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
