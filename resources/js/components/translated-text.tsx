import { useT } from '@/lib/i18n';
import { type Translatable } from '@/types';
import { Languages } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    item: Translatable;
    fetched?: Record<string, string>;
    original: { title?: string | null; body: string };
    /** Long texts start folded, with "Read more". */
    clamp?: boolean;
    titleClassName?: string;
    /** The translated field shown as the body: "body" for posts, "description" for events and videos. */
    bodyField?: string;
}

/** Shows content in the reader's language, with a switch back to the original. */
export function TranslatedText({ item, fetched, original, clamp = false, titleClassName = 'font-semibold', bodyField = 'body' }: Props) {
    const t = useT();
    const [showOriginal, setShowOriginal] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const fields = { ...(item.translation?.fields ?? {}), ...(fetched ?? {}) };
    const hasTranslation = Object.keys(fields).length > 0;
    const waiting = item.translation?.pending && !fetched;

    const title = !showOriginal && fields.title ? fields.title : original.title;
    const body = !showOriginal && fields[bodyField] ? fields[bodyField] : original.body;
    const long = clamp && body.length > 600;
    const bodyRef = useRef<HTMLDivElement>(null);

    // Old posts link to pictures on sites that no longer exist: hide those instead of showing a broken icon.
    useEffect(() => {
        bodyRef.current?.querySelectorAll('img').forEach((img) => {
            const hide = () => (img.style.display = 'none');
            if (img.complete && img.naturalWidth === 0) hide();
            else img.addEventListener('error', hide, { once: true });
        });
    }, [body]);

    return (
        <div className="space-y-2">
            {title && (
                <h3 dir="auto" className={titleClassName}>
                    {title}
                </h3>
            )}
            <div
                ref={bodyRef}
                dir="auto"
                className={`prose prose-sm prose-slate dark:prose-invert prose-a:text-brand prose-img:rounded-xl max-w-none break-words ${long && !expanded ? 'line-clamp-5' : ''}`}
                dangerouslySetInnerHTML={{ __html: body }}
            />
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                {long && (
                    <button type="button" className="text-brand font-medium hover:underline" onClick={() => setExpanded(!expanded)}>
                        {expanded ? t('Collapse') : t('Read more')}
                    </button>
                )}
                {(hasTranslation || waiting) && (
                    <span className="text-muted-foreground inline-flex items-center gap-1">
                        <Languages className="size-3" />
                        {waiting ? (
                            <span>{t('Translating…')}</span>
                        ) : (
                            <button type="button" className="hover:underline" onClick={() => setShowOriginal(!showOriginal)}>
                                {showOriginal ? t('Show translation') : t('Translated automatically · Show original')}
                            </button>
                        )}
                    </span>
                )}
            </div>
        </div>
    );
}
