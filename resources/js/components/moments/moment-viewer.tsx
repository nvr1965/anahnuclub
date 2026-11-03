import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import { useT } from '@/lib/i18n';
import { shortAgo } from '@/lib/relative-time';
import { type MomentItem, type Person, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Archive, Eye, Languages, Send, Trash2, Volume2, VolumeX, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MomentWords, STILL_MS } from './moment-look';

interface Loaded {
    user: Person;
    mine: boolean;
    can_reply: boolean;
    moments: MomentItem[];
}

const REACTIONS = ['❤️', '😂', '😮', '😢', '👏', '🔥'];

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

async function send(url: string, method: string, body?: object) {
    return fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : undefined,
    });
}

/**
 * Full-screen moments, one person after another. Tap the far edge to go on, the near edge to go back,
 * hold to pause. In Hebrew and Arabic "on" is to the left, as the pages turn there.
 * Always dark and full-screen, so it looks the same whichever style of the site is chosen.
 */
export default function MomentViewer({ people, start, onClose }: { people: Person[]; start: number; onClose: () => void }) {
    const t = useT();
    const { dir, locale } = usePage<SharedData>().props;
    const rtl = dir === 'rtl';
    const [person, setPerson] = useState(start);
    const [data, setData] = useState<Loaded | null>(null);
    const [index, setIndex] = useState(0);
    const [progress, setProgress] = useState(0);
    const [holding, setHolding] = useState(false);
    const [typing, setTyping] = useState(false);
    const [viewers, setViewers] = useState<(Person & { viewed_at: string | null })[] | null>(null);
    const [reply, setReply] = useState('');
    const [notice, setNotice] = useState('');
    const [muted, setMuted] = useState(false);
    const [showOriginal, setShowOriginal] = useState(false);
    const videoRef = useRef<HTMLVideoElement>(null);
    const pressed = useRef<{ at: number; x: number } | null>(null);
    const holdTimer = useRef<number | undefined>(undefined);

    const paused = holding || typing || viewers !== null;
    const moment = data?.moments[index];

    // One person's moments, from the first not watched yet.
    useEffect(() => {
        const who = people[person];
        if (!who) return onClose();
        let cancelled = false;
        setData(null);
        fetch(route('moments.user', who.id), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((loaded: Loaded | null) => {
                if (cancelled) return;
                if (!loaded || loaded.moments.length === 0) return next(true);
                const first = loaded.moments.findIndex((m) => !m.seen);
                setData(loaded);
                setIndex(first < 0 ? 0 : first);
                setProgress(0);
            })
            .catch(() => !cancelled && onClose());
        return () => void (cancelled = true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [person]);

    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => void (document.body.style.overflow = '');
    }, []);

    const next = useCallback(
        (skipPerson = false) => {
            setProgress(0);
            setShowOriginal(false);
            if (!skipPerson && data && index < data.moments.length - 1) return setIndex(index + 1);
            if (person < people.length - 1) return setPerson(person + 1);
            onClose();
        },
        [data, index, person, people.length, onClose],
    );

    const back = useCallback(() => {
        setProgress(0);
        setShowOriginal(false);
        if (index > 0) return setIndex(index - 1);
        if (person > 0) setPerson(person - 1);
    }, [index, person]);

    // Watched: the ring turns grey, and the author sees who looked.
    useEffect(() => {
        if (moment && data && !data.mine && !moment.seen) {
            send(route('moments.view', moment.id), 'POST');
            moment.seen = true;
        }
        setViewers(null);
    }, [moment, data]);

    // Photos and words stay a few seconds; a video as long as it plays.
    useEffect(() => {
        if (!moment || moment.kind === 'video' || paused) return;
        let last = performance.now();
        let frame = 0;
        const tick = (now: number) => {
            const step = (now - last) / STILL_MS;
            last = now;
            setProgress((p) => {
                if (p + step >= 1) {
                    window.setTimeout(() => next(), 0);
                    return 1;
                }
                return p + step;
            });
            frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [moment, paused, next]);

    useEffect(() => {
        const video = videoRef.current;
        if (!video) return;
        if (paused) return video.pause();
        // Browsers may refuse sound until the page is touched: then it plays silently, with a button for sound.
        video.play().catch(() => {
            video.muted = true;
            setMuted(true);
            video.play().catch(() => undefined);
        });
    }, [paused, moment]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (typing) return;
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowRight') (rtl ? back : next)();
            if (e.key === 'ArrowLeft') (rtl ? next : back)();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [next, back, onClose, rtl, typing]);

    // Words written in another language come in the reader's own.
    const items = useMemo(() => (data?.moments ?? []).filter((m) => m.text), [data]);
    const fetched = useAutoTranslate(items, locale);
    const translated = moment ? (fetched('moment', moment.id)?.text ?? moment.translation?.fields?.text) : undefined;
    const words = moment?.text ? (!showOriginal && translated ? translated : moment.text) : null;

    const down = (e: React.PointerEvent) => {
        pressed.current = { at: Date.now(), x: e.clientX };
        holdTimer.current = window.setTimeout(() => setHolding(true), 220);
    };
    const up = (e: React.PointerEvent) => {
        window.clearTimeout(holdTimer.current);
        const press = pressed.current;
        pressed.current = null;
        setHolding(false);
        if (!press || Date.now() - press.at > 300) return;
        const box = (e.currentTarget as HTMLElement).getBoundingClientRect();
        const fromStart = rtl ? box.right - e.clientX : e.clientX - box.left;
        (fromStart < box.width / 3 ? back : next)();
    };

    const answer = async (body: string) => {
        if (!moment || !body.trim()) return;
        const response = await send(route('moments.reply', moment.id), 'POST', { body });
        setNotice(response.ok ? t('Sent to messages') : t('Could not send'));
        setReply('');
        setTyping(false);
        (document.activeElement as HTMLElement | null)?.blur();
        window.setTimeout(() => setNotice(''), 2000);
    };

    const remove = async () => {
        if (!moment || !data) return;
        await send(route('moments.destroy', moment.id), 'DELETE');
        const left = data.moments.filter((m) => m.id !== moment.id);
        if (left.length === 0) return next(true);
        setData({ ...data, moments: left });
        setIndex(Math.min(index, left.length - 1));
        setProgress(0);
    };

    const showViewers = async () => {
        if (!moment) return;
        setViewers([]);
        const response = await fetch(route('moments.viewers', moment.id), { headers: { Accept: 'application/json' } });
        if (response.ok) setViewers((await response.json()).viewers);
    };

    return (
        <div
            className="fixed inset-0 z-[70] flex items-center justify-center bg-black text-white"
            dir={dir}
            role="dialog"
            aria-modal="true"
            aria-label={t('Moments')}
        >
            <div className="relative h-full w-full overflow-hidden sm:aspect-[9/16] sm:h-[min(100dvh,920px)] sm:w-auto sm:rounded-xl">
                {/* The moment itself; taps on it move on or back, holding pauses. */}
                <div
                    className="absolute inset-0 touch-none select-none"
                    onPointerDown={down}
                    onPointerUp={up}
                    onPointerLeave={() => (window.clearTimeout(holdTimer.current), setHolding(false))}
                    onContextMenu={(e) => e.preventDefault()}
                >
                    {!moment ? (
                        <div className="flex h-full items-center justify-center">
                            <span className="size-8 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                        </div>
                    ) : moment.kind === 'photo' ? (
                        <img src={moment.file_url ?? undefined} alt="" className="h-full w-full object-contain" draggable={false} />
                    ) : moment.kind === 'video' ? (
                        <video
                            key={moment.id}
                            ref={videoRef}
                            src={moment.file_url ?? undefined}
                            poster={moment.thumb ?? undefined}
                            playsInline
                            autoPlay
                            muted={muted}
                            preload="auto"
                            className="h-full w-full object-contain"
                            onTimeUpdate={(e) => setProgress(e.currentTarget.currentTime / (e.currentTarget.duration || moment.duration || 30))}
                            onEnded={() => next()}
                        />
                    ) : null}
                    {moment && words && (
                        <MomentWords text={words} kind={moment.kind} position={moment.text_position} background={moment.background} />
                    )}
                </div>

                {/* Bars for each moment of this person, then who and when. */}
                <div className="pointer-events-none absolute inset-x-0 top-0 bg-gradient-to-b from-black/60 to-transparent px-3 pt-[max(10px,env(safe-area-inset-top))] pb-6">
                    <div className="flex gap-1">
                        {(data?.moments ?? []).map((m, i) => (
                            <div key={m.id} className="h-[3px] flex-1 overflow-hidden rounded-full bg-white/35">
                                <div
                                    className="h-full bg-white"
                                    style={{ width: `${i < index ? 100 : i === index ? Math.min(100, progress * 100) : 0}%` }}
                                />
                            </div>
                        ))}
                    </div>
                    <div className="pointer-events-auto mt-3 flex items-center gap-2">
                        {data && (
                            <Link href={route('people.show', data.user.id)} className="flex min-w-0 items-center gap-2">
                                <Avatar className="size-9 border border-white/40">
                                    <AvatarImage src={data.user.avatar ?? undefined} alt="" className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                                <span dir="auto" className="truncate text-sm font-semibold">
                                    {data.mine ? t('Your moments') : data.user.name}
                                </span>
                            </Link>
                        )}
                        {moment && <span className="text-xs text-white/75">{shortAgo(moment.created_at, locale)}</span>}
                        <span className="flex-1" />
                        {moment?.kind === 'video' && (
                            <button
                                type="button"
                                onClick={() => setMuted(!muted)}
                                className="rounded-full p-2 hover:bg-white/15"
                                aria-label={muted ? t('Sound on') : t('Sound off')}
                            >
                                {muted ? <VolumeX className="size-5" /> : <Volume2 className="size-5" />}
                            </button>
                        )}
                        {moment?.can_delete && (
                            <button
                                type="button"
                                onClick={remove}
                                className="rounded-full p-2 hover:bg-white/15"
                                aria-label={t('Delete')}
                                title={t('Delete')}
                            >
                                <Trash2 className="size-5" />
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full p-2 hover:bg-white/15"
                            aria-label={t('Close')}
                            title={t('Close')}
                        >
                            <X className="size-6" />
                        </button>
                    </div>
                    {moment && translated && (
                        <button
                            type="button"
                            onClick={() => setShowOriginal(!showOriginal)}
                            className="pointer-events-auto mt-2 inline-flex items-center gap-1 rounded-full bg-black/40 px-2.5 py-1 text-xs"
                        >
                            <Languages className="size-3.5" />
                            {showOriginal ? t('Show translation') : t('Show original')}
                        </button>
                    )}
                </div>

                {notice && (
                    <div className="absolute inset-x-0 top-1/2 flex justify-center">
                        <span className="rounded-full bg-black/70 px-4 py-2 text-sm">{notice}</span>
                    </div>
                )}

                {/* Below: your own moment shows who watched; someone else's takes an answer. */}
                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-3 pt-8 pb-[max(12px,env(safe-area-inset-bottom))]">
                    {data?.mine && moment && (
                        <div className="flex items-center justify-between">
                            <button
                                type="button"
                                onClick={showViewers}
                                className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold"
                            >
                                <Eye className="size-4" /> {t('Viewers')}: {moment.views ?? 0}
                            </button>
                            <Link
                                href={route('moments.archive')}
                                className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold"
                            >
                                <Archive className="size-4" /> {t('Archive')}
                            </Link>
                        </div>
                    )}
                    {data && !data.mine && data.can_reply && moment && (
                        <div className="space-y-2">
                            {!typing && (
                                <div className="flex justify-center gap-3 text-2xl">
                                    {REACTIONS.map((emoji) => (
                                        <button
                                            key={emoji}
                                            type="button"
                                            onClick={() => answer(emoji)}
                                            className="transition hover:scale-125"
                                            aria-label={emoji}
                                        >
                                            {emoji}
                                        </button>
                                    ))}
                                </div>
                            )}
                            <form onSubmit={(e) => (e.preventDefault(), answer(reply))} className="flex items-center gap-2">
                                <input
                                    dir="auto"
                                    value={reply}
                                    maxLength={1000}
                                    onChange={(e) => setReply(e.target.value)}
                                    onFocus={() => setTyping(true)}
                                    onBlur={() => !reply && setTyping(false)}
                                    placeholder={t('Reply…')}
                                    className="min-w-0 flex-1 rounded-full border border-white/50 bg-transparent px-4 py-2.5 text-[15px] text-white outline-none placeholder:text-white/70"
                                />
                                {reply.trim() && (
                                    <button type="submit" className="rounded-full p-2 hover:bg-white/15" aria-label={t('Send')}>
                                        <Send className="size-5 rtl:-scale-x-100" />
                                    </button>
                                )}
                            </form>
                        </div>
                    )}
                </div>

                {viewers !== null && (
                    <div className="absolute inset-x-0 bottom-0 max-h-[60%] overflow-y-auto rounded-t-2xl bg-white p-4 text-slate-900 dark:bg-neutral-900 dark:text-white">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="font-semibold">
                                {t('Viewers')}: {moment?.views ?? 0}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setViewers(null)}
                                className="rounded-full p-1.5 hover:bg-black/5"
                                aria-label={t('Close')}
                            >
                                <X className="size-5" />
                            </button>
                        </div>
                        {viewers.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('Nobody has watched it yet.')}</p>
                        ) : (
                            <ul className="space-y-2">
                                {viewers.map((v) => (
                                    <li key={v.id}>
                                        <Link href={route('people.show', v.id)} className="flex items-center gap-3">
                                            <Avatar className="size-9">
                                                <AvatarImage src={v.avatar ?? undefined} alt="" className="object-cover" />
                                                <DefaultAvatarFallback />
                                            </Avatar>
                                            <span dir="auto" className="flex-1 truncate text-sm font-medium">
                                                {v.name}
                                            </span>
                                            <span className="text-muted-foreground text-xs">{shortAgo(v.viewed_at, locale)}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
