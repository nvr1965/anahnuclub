import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useCreateRequest } from '@/lib/create';
import { useT } from '@/lib/i18n';
import { type MomentCover, type SharedData, type MomentTray as Tray } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { BACKGROUNDS } from './moment-look';

// The viewer and the editor load only when someone opens them: the feed stays as light as before.
const MomentViewer = lazy(() => import('./moment-viewer'));
const MomentComposer = lazy(() => import('./moment-composer'));

/** One tall card of the strip, the size of Facebook's. */
const card = 'relative h-[11.5rem] w-[6.5rem] shrink-0 snap-start overflow-hidden rounded-xl sm:h-[12.5rem] sm:w-[7rem]';

/**
 * The strip of tall cards above the feed, as on Facebook: "Create a moment" with your photo first,
 * then a card for each person with their newest moment behind their face and name.
 * Those with something not watched yet have a ring in the site's colour; the rest a grey one.
 */
export function MomentTray({ tray }: { tray: Tray }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const [watching, setWatching] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);
    const strip = useRef<HTMLDivElement>(null);
    const [more, setMore] = useState({ back: false, on: false });
    // "Moment" in the "+" menu of the top bar opens the editor here.
    useCreateRequest('moment', () => setAdding(true));

    const me = auth.user;
    const people = me ? (tray.mine > 0 ? tray.people : tray.people.filter((p) => p.user.id !== me.id)) : [];

    // Arrows on wide screens, when there are more cards than fit.
    useEffect(() => {
        const el = strip.current;
        if (!el) return;
        const check = () => {
            const at = Math.abs(el.scrollLeft);
            setMore({ back: at > 8, on: at + el.clientWidth < el.scrollWidth - 8 });
        };
        check();
        el.addEventListener('scroll', check, { passive: true });
        window.addEventListener('resize', check);
        return () => (el.removeEventListener('scroll', check), window.removeEventListener('resize', check));
    }, [people.length]);

    if (!me) return null;

    // The viewer moves on from one person to the next, in the strip's order (yours first, when you have some).
    const open = (userId: number) => setWatching(people.findIndex((p) => p.user.id === userId));
    const refresh = () => router.reload({ only: ['moments'] });
    const scroll = (forward: boolean) => {
        const el = strip.current;
        if (!el) return;
        const rtl = getComputedStyle(el).direction === 'rtl';
        el.scrollBy({ left: (forward !== rtl ? 1 : -1) * el.clientWidth * 0.8, behavior: 'smooth' });
    };
    const arrow =
        'absolute top-1/2 z-10 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-700 shadow-md ring-1 ring-black/5 hover:bg-slate-50 sm:flex dark:bg-neutral-800 dark:text-neutral-100';

    return (
        <section
            aria-label={t('Moments')}
            className="moment-tray flat:rounded-none flat:shadow-none stream:border-b stream:border-slate-200 dark:stream:border-neutral-800 relative rounded-lg bg-white px-2 py-3 shadow-sm dark:bg-neutral-900"
        >
            <div ref={strip} className="flex snap-x gap-2 overflow-x-auto px-1 [scrollbar-width:none]">
                <button
                    type="button"
                    onClick={() => setAdding(true)}
                    className={`${card} group flex flex-col border border-slate-200 bg-white text-start dark:border-neutral-700 dark:bg-neutral-800`}
                >
                    <span className="relative block w-full flex-1 overflow-hidden bg-slate-100 dark:bg-neutral-700">
                        {me.avatar ? (
                            <img src={me.avatar} alt="" className="size-full object-cover transition-transform duration-300 group-hover:scale-105" />
                        ) : (
                            <Avatar className="size-full rounded-none">
                                <DefaultAvatarFallback />
                            </Avatar>
                        )}
                    </span>
                    <span className="relative flex h-[3.75rem] w-full shrink-0 items-end justify-center px-1 pb-2">
                        <span className="bg-brand absolute -top-[1.125rem] left-1/2 flex size-9 -translate-x-1/2 items-center justify-center rounded-full border-4 border-white text-white dark:border-neutral-800">
                            <Plus className="size-5" strokeWidth={3} />
                        </span>
                        <span className="line-clamp-2 text-center text-[0.8125rem] leading-tight font-semibold">{t('Create a moment')}</span>
                    </span>
                </button>

                {people.map((p) => (
                    <button
                        key={p.user.id}
                        type="button"
                        onClick={() => open(p.user.id)}
                        className={`${card} group bg-slate-800 text-start`}
                        aria-label={p.user.id === me.id ? t('Your moments') : p.user.name}
                    >
                        <Cover cover={p.cover} />
                        <span className="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/70" />
                        <span
                            className={`absolute start-2 top-2 block rounded-full p-[3px] ${p.unseen ? 'bg-brand' : 'bg-white/70 dark:bg-neutral-400'}`}
                        >
                            <Avatar className="size-8 border-2 border-slate-800">
                                <AvatarImage src={p.user.avatar ?? undefined} alt="" className="object-cover" />
                                <DefaultAvatarFallback />
                            </Avatar>
                        </span>
                        <span
                            dir="auto"
                            className="absolute inset-x-2 bottom-2 line-clamp-2 text-[0.8125rem] leading-tight font-semibold text-white [text-shadow:0_1px_2px_rgba(0,0,0,.6)]"
                        >
                            {p.user.id === me.id ? t('Your moments') : p.user.name}
                        </span>
                    </button>
                ))}

                {people.length === 0 && (
                    <p className="text-muted-foreground flex max-w-56 items-center px-2 text-xs leading-snug">
                        {t('Moments of your friends will appear here for a day.')}
                    </p>
                )}
            </div>

            {more.back && (
                <button type="button" onClick={() => scroll(false)} aria-label={t('Back')} className={`${arrow} start-3`}>
                    <ChevronRight className="size-5 -scale-x-100 rtl:scale-x-100" />
                </button>
            )}
            {more.on && (
                <button type="button" onClick={() => scroll(true)} aria-label={t('More')} className={`${arrow} end-3`}>
                    <ChevronRight className="size-5 rtl:-scale-x-100" />
                </button>
            )}

            <Suspense fallback={null}>
                {watching !== null && watching >= 0 && (
                    <MomentViewer people={people.map((p) => p.user)} start={watching} onClose={() => (setWatching(null), refresh())} />
                )}
                {adding && <MomentComposer lists={tray.lists} onClose={() => setAdding(false)} />}
            </Suspense>
        </section>
    );
}

/** The newest moment behind the card: its picture, or its words on their colour. */
function Cover({ cover }: { cover: MomentCover }) {
    if (cover.thumb) {
        return (
            <img
                src={cover.thumb}
                alt=""
                loading="lazy"
                className="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
        );
    }

    return (
        <span
            className="absolute inset-0 flex items-center justify-center p-3 transition-transform duration-300 group-hover:scale-105"
            style={{ background: BACKGROUNDS[cover.background ?? 'brand'] ?? BACKGROUNDS.brand }}
        >
            {cover.text && (
                <span dir="auto" className="line-clamp-5 text-center text-xs leading-snug font-bold break-words text-white">
                    {cover.text}
                </span>
            )}
        </span>
    );
}
