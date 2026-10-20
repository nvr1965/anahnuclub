import { useT } from '@/lib/i18n';
import { type LinkCard } from '@/types';
import { X } from 'lucide-react';
import { useState } from 'react';

/**
 * A shared link drawn as on Facebook: a big picture above the site, title and summary,
 * or, when the page only offers a small picture, a thumbnail beside them.
 */
export function LinkPreview({ link, onRemove }: { link: LinkCard; onRemove?: () => void }) {
    const [image, setImage] = useState<'loading' | 'big' | 'small' | 'none'>(link.image ? 'loading' : 'none');
    const t = useT();
    const small = image === 'small' || image === 'none';
    const card = (
        <a
            href={link.url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            className={`overflow-hidden border-y border-slate-200 bg-slate-100 transition hover:bg-slate-200/70 dark:border-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700/70 ${small ? 'flex items-center' : 'block'}`}
        >
            {link.image && image !== 'none' && (
                <img
                    src={link.image}
                    alt=""
                    loading="lazy"
                    referrerPolicy="no-referrer"
                    className={small ? 'size-24 shrink-0 object-cover sm:size-32' : 'aspect-[1.91/1] w-full object-cover'}
                    onLoad={(e) => setImage(e.currentTarget.naturalWidth >= 400 ? 'big' : 'small')}
                    onError={() => setImage('none')}
                />
            )}
            <div dir="auto" className="min-w-0 px-4 py-2.5">
                <div className="text-muted-foreground truncate text-[13px] uppercase">{link.domain}</div>
                {link.title && <div className="line-clamp-2 text-[17px] leading-snug font-semibold">{link.title}</div>}
                {link.description && <div className="text-muted-foreground mt-0.5 line-clamp-1 text-[15px]">{link.description}</div>}
            </div>
        </a>
    );
    if (!onRemove) return card;

    // While writing a post the card can be closed; the link then stays in the text only.
    return (
        <div className="relative overflow-hidden rounded-lg">
            {card}
            <button
                type="button"
                aria-label={t('Remove')}
                onClick={onRemove}
                className="absolute end-2 top-2 rounded-full bg-black/60 p-1.5 text-white hover:bg-black/80"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
