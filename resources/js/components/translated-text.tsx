import { useT } from '@/lib/i18n';
import { type Translatable } from '@/types';
import { Languages } from 'lucide-react';
import { useState } from 'react';

interface Props {
    item: Translatable;
    fetched?: Record<string, string>;
    original: { title?: string | null; body: string };
}

/** Shows content in the reader's language, with a switch back to the original. */
export function TranslatedText({ item, fetched, original }: Props) {
    const t = useT();
    const [showOriginal, setShowOriginal] = useState(false);
    const fields = { ...(item.translation?.fields ?? {}), ...(fetched ?? {}) };
    const hasTranslation = Object.keys(fields).length > 0;
    const waiting = item.translation?.pending && !fetched;

    const title = !showOriginal && fields.title ? fields.title : original.title;
    const body = !showOriginal && fields.body ? fields.body : original.body;

    return (
        <div className="space-y-2">
            {title && <h3 dir="auto" className="font-semibold">{title}</h3>}
            <div dir="auto" className="prose prose-sm dark:prose-invert max-w-none break-words" dangerouslySetInnerHTML={{ __html: body }} />
            {(hasTranslation || waiting) && (
                <div className="text-muted-foreground flex items-center gap-1 text-xs">
                    <Languages className="size-3" />
                    {waiting ? (
                        <span>{t('Translating…')}</span>
                    ) : (
                        <button type="button" className="hover:underline" onClick={() => setShowOriginal(!showOriginal)}>
                            {showOriginal ? t('Show translation') : t('Translated automatically · Show original')}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
