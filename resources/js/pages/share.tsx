import { PostComposer } from '@/components/post-composer';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type Sidebar } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

/** What the "Share on anahnu.club" button of another site opens: the composer with that page's link. */
export default function Share({ body, url, sidebar }: { body: string; url: string | null; sidebar: Sidebar }) {
    const t = useT();
    const [posted, setPosted] = useState(false);
    // The button opens a small window; when it did, closing it returns the reader to the site.
    const popup = typeof window !== 'undefined' && !!window.opener;
    const host = url ? new URL(url).host : null;
    const leave = () => (popup ? window.close() : url ? (window.location.href = url) : router.visit(route('dashboard')));
    const button = 'rounded-md px-4 py-2 text-sm font-semibold whitespace-nowrap';

    return (
        <AppLayout sidebar={sidebar}>
            <Head title={t('Share on anahnu.club')} />
            <div className="space-y-4">
                <h1 className="text-lg font-bold">{t('Share on anahnu.club')}</h1>
                {posted ? (
                    <div className="rounded-lg bg-white p-6 text-center shadow-sm dark:bg-neutral-900">
                        <CheckCircle2 className="mx-auto mb-2 size-10 text-green-600" />
                        <p className="mb-4 text-[15px] font-semibold">{t('Posted. Your friends will see it in the feed.')}</p>
                        <div className="flex flex-wrap justify-center gap-2">
                            {(popup || host) && (
                                <button type="button" onClick={leave} className={`bg-brand hover:bg-brand-dark text-white ${button}`}>
                                    {popup ? t('Close window') : t('Back to :site', { site: host ?? '' })}
                                </button>
                            )}
                            <Link href={route('dashboard')} className={`bg-slate-100 hover:bg-slate-200 dark:bg-neutral-800 ${button}`}>
                                {t('Go to the feed')}
                            </Link>
                        </div>
                    </div>
                ) : (
                    <PostComposer initialBody={body} onPosted={() => setPosted(true)} onCancel={leave} />
                )}
            </div>
        </AppLayout>
    );
}
