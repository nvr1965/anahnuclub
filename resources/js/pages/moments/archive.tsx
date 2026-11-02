import { MomentWords } from '@/components/moments/moment-look';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type MomentItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Eye, Repeat2, Trash2 } from 'lucide-react';

type Archived = MomentItem & { active: boolean; views: number; deleted_at: string };

/** Your moments of the last few days: the ones still up, and the ones gone, to share again before they are deleted. */
export default function MomentArchive({ moments, archive_days }: { moments: Archived[]; archive_days: number }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title={t('Moments archive')} />
            <div className="space-y-4">
                <div>
                    <h1 className="text-2xl font-bold">{t('Moments archive')}</h1>
                    <p className="text-muted-foreground text-sm">
                        {t('Only you see this. Moments are kept here for :n days after they disappear.', { n: archive_days })}
                    </p>
                </div>
                {moments.length === 0 ? (
                    <p className="text-muted-foreground rounded-lg bg-white p-8 text-center shadow-sm dark:bg-neutral-900">
                        {t('No moments in the last few days.')}
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {moments.map((m) => (
                            <figure key={m.id} className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
                                <div className="relative aspect-[9/16] bg-black [&_p]:!text-base">
                                    {m.kind === 'photo' && (
                                        <img src={m.file_url ?? undefined} alt="" loading="lazy" className="h-full w-full object-contain" />
                                    )}
                                    {m.kind === 'video' && (
                                        <video
                                            src={m.file_url ?? undefined}
                                            poster={m.thumb ?? undefined}
                                            controls
                                            preload="none"
                                            playsInline
                                            className="h-full w-full object-contain"
                                        />
                                    )}
                                    {m.text && <MomentWords text={m.text} kind={m.kind} position={m.text_position} background={m.background} />}
                                    <span
                                        className={`absolute start-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold text-white ${m.active ? 'bg-brand' : 'bg-black/60'}`}
                                    >
                                        {m.active ? t('Up now') : t('Gone')}
                                    </span>
                                </div>
                                <figcaption className="space-y-2 p-2.5 text-xs">
                                    <div className="text-muted-foreground flex items-center justify-between gap-2">
                                        <span>{relativeTime(m.created_at, locale)}</span>
                                        <span className="inline-flex items-center gap-1">
                                            <Eye className="size-3.5" /> {m.views}
                                        </span>
                                    </div>
                                    {!m.active && (
                                        <p className="text-muted-foreground">{t('Deleted :when', { when: relativeTime(m.deleted_at, locale) })}</p>
                                    )}
                                    <div className="flex gap-1.5">
                                        <button
                                            type="button"
                                            onClick={() => router.post(route('moments.repost', m.id))}
                                            className="bg-brand hover:bg-brand-dark inline-flex flex-1 items-center justify-center gap-1 rounded-md px-2 py-1.5 font-semibold text-white"
                                        >
                                            <Repeat2 className="size-3.5" /> {t('Share again')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => router.delete(route('moments.destroy', m.id), { preserveScroll: true })}
                                            className="rounded-md bg-slate-100 px-2 py-1.5 hover:bg-slate-200 dark:bg-neutral-800"
                                            aria-label={t('Delete')}
                                            title={t('Delete')}
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </div>
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
