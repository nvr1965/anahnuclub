import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/** "Embed post", as on Facebook: the <iframe> code for another site, with how it will look there. */
export function EmbedPostDialog({ open, onClose, postId, src }: { open: boolean; onClose: () => void; postId: number; src: string }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const frame = useRef<HTMLIFrameElement>(null);
    const [height, setHeight] = useState(500);
    const [copied, setCopied] = useState(false);
    const url = `${src}?lang=${locale}`;
    // The post measures itself once loaded; the code then carries its real height.
    const code = `<iframe src="${url}" width="500" height="${height}" style="border:none;overflow:hidden;max-width:100%" scrolling="no" frameborder="0" loading="lazy" allowfullscreen="true" title="anahnu.club"></iframe>`;

    useEffect(() => {
        const listen = (e: MessageEvent) => {
            if (e.source === frame.current?.contentWindow && e.data?.type === 'anahnu-embed' && e.data.post === postId) setHeight(e.data.height);
        };
        window.addEventListener('message', listen);
        return () => window.removeEventListener('message', listen);
    }, [postId]);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(code);
        } catch {
            window.prompt(t('Copy code'), code);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-xl">
                <div className="border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                    <DialogTitle className="text-center text-lg">{t('Embed post')}</DialogTitle>
                </div>
                <div className="space-y-3 p-4">
                    <DialogDescription className="text-foreground text-[15px]">{t('Copy and paste this code into your site.')}</DialogDescription>
                    <input
                        readOnly
                        dir="ltr"
                        value={code}
                        onFocus={(e) => e.currentTarget.select()}
                        className="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <div className="max-h-[50vh] overflow-y-auto rounded-md bg-slate-100 p-3 dark:bg-neutral-800">
                        <iframe
                            ref={frame}
                            src={url}
                            title={t('Embed post')}
                            className="mx-auto block w-full max-w-[500px] border-0"
                            style={{ height }}
                        />
                    </div>
                    <button type="button" onClick={copy} className="bg-brand hover:bg-brand-dark w-full rounded-md py-2 font-semibold text-white">
                        {copied ? t('Code copied') : t('Copy code')}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
