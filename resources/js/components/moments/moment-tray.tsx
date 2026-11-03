import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type SharedData, type MomentTray as Tray } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

// The viewer and the editor load only when someone opens them: the feed stays as light as before.
const MomentViewer = lazy(() => import('./moment-viewer'));
const MomentComposer = lazy(() => import('./moment-composer'));

/**
 * The strip of round faces above the feed: your own first, then friends with something new, then the rest.
 * Colours and corners come from the theme, so it fits whichever look of the site is chosen.
 */
export function MomentTray({ tray }: { tray: Tray }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const [watching, setWatching] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);
    const me = auth.user;
    if (!me) return null;

    const others = tray.people.filter((p) => p.user.id !== me.id);
    // The viewer moves on from one person to the next, in the strip's order (yours first, when you have some).
    const queue = tray.mine > 0 ? tray.people : others;
    const open = (userId: number) => setWatching(queue.findIndex((p) => p.user.id === userId));
    const refresh = () => router.reload({ only: ['moments'] });

    return (
        <section
            aria-label={t('Moments')}
            className="moment-tray flat:rounded-none flat:shadow-none stream:border-b stream:border-slate-200 dark:stream:border-neutral-800 rounded-lg bg-white px-2 py-3 shadow-sm dark:bg-neutral-900"
        >
            <div className="flex snap-x gap-3 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
                <div className="flex w-[72px] shrink-0 snap-start flex-col items-center gap-1.5">
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => (tray.mine > 0 ? open(me.id) : setAdding(true))}
                            aria-label={tray.mine > 0 ? t('Your moments') : t('Add a moment')}
                            className={`block rounded-full p-[3px] ${tray.mine > 0 ? 'bg-gradient-to-tr from-amber-400 via-rose-500 to-fuchsia-600' : 'bg-transparent'}`}
                        >
                            <span className="block rounded-full bg-white p-[2px] dark:bg-neutral-900">
                                <Avatar className="size-14">
                                    <AvatarImage src={me.avatar} alt="" className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setAdding(true)}
                            aria-label={t('Add a moment')}
                            title={t('Add a moment')}
                            className="bg-brand absolute -end-0.5 -bottom-0.5 flex size-6 items-center justify-center rounded-full border-2 border-white text-white dark:border-neutral-900"
                        >
                            <Plus className="size-4" strokeWidth={3} />
                        </button>
                    </div>
                    <span className="line-clamp-2 w-full text-center text-xs leading-tight">{tray.mine > 0 ? t('You') : t('Add a moment')}</span>
                </div>

                {others.map((p) => (
                    <button
                        key={p.user.id}
                        type="button"
                        onClick={() => open(p.user.id)}
                        className="flex w-[72px] shrink-0 snap-start flex-col items-center gap-1.5"
                    >
                        <span
                            className={`block rounded-full p-[3px] ${p.unseen ? 'bg-gradient-to-tr from-amber-400 via-rose-500 to-fuchsia-600' : 'bg-slate-300 dark:bg-neutral-600'}`}
                        >
                            <span className="block rounded-full bg-white p-[2px] dark:bg-neutral-900">
                                <Avatar className="size-14">
                                    <AvatarImage src={p.user.avatar ?? undefined} alt="" className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                            </span>
                        </span>
                        <span dir="auto" className={`w-full truncate text-center text-xs ${p.unseen ? 'font-semibold' : 'text-muted-foreground'}`}>
                            {p.user.name.split(' ')[0]}
                        </span>
                    </button>
                ))}

                {others.length === 0 && (
                    <p className="text-muted-foreground flex max-w-56 items-center text-xs leading-snug">
                        {t('Moments of your friends will appear here for a day.')}
                    </p>
                )}
            </div>

            <Suspense fallback={null}>
                {watching !== null && watching >= 0 && (
                    <MomentViewer people={queue.map((p) => p.user)} start={watching} onClose={() => (setWatching(null), refresh())} />
                )}
                {adding && <MomentComposer lists={tray.lists} onClose={() => setAdding(false)} />}
            </Suspense>
        </section>
    );
}
