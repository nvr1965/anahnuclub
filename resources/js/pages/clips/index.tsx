import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import { useT } from '@/lib/i18n';
import { type ClipItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, Heart, MessageCircle, Play, Plus, Share2, Trash2, Volume2, VolumeX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** Phones that asked to save data do not start videos on their own. */
const saveData = () =>
    typeof navigator !== 'undefined' && Boolean((navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData);

/**
 * Clips: one vertical video per screen, swipe up for the next. Only the clip on screen and its neighbours
 * have a video element; the rest are pictures, so a long list costs a phone nothing.
 * Always dark and full-screen, the same in every style of the site.
 */
export default function Clips({ clips: first, next: firstNext, skip }: { clips: ClipItem[]; next: number | null; skip: number | null }) {
    const t = useT();
    const { dir, locale } = usePage<SharedData>().props;
    const [clips, setClips] = useState(first);
    const [next, setNext] = useState(firstNext);
    const [current, setCurrent] = useState(0);
    const [muted, setMuted] = useState(true);
    const loading = useRef(false);
    const scroller = useRef<HTMLDivElement>(null);
    const translations = useAutoTranslate(clips, locale);

    // The clip that fills most of the screen is the one playing.
    useEffect(() => {
        const root = scroller.current;
        if (!root) return;
        const observer = new IntersectionObserver(
            (entries) => entries.forEach((e) => e.isIntersecting && setCurrent(Number((e.target as HTMLElement).dataset.index))),
            { root, threshold: 0.6 },
        );
        root.querySelectorAll('[data-index]').forEach((el) => observer.observe(el));
        return () => observer.disconnect();
    }, [clips.length]);

    // More clips before the end is reached.
    useEffect(() => {
        if (!next || loading.current || current < clips.length - 3) return;
        loading.current = true;
        fetch(route('clips.more', { before: next, ...(skip ? { skip } : {}) }), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((more: { clips: ClipItem[]; next: number | null }) => {
                setClips((list) => [...list, ...more.clips.filter((c) => !list.some((x) => x.id === c.id))]);
                setNext(more.next);
            })
            .catch(() => undefined)
            .finally(() => (loading.current = false));
    }, [current, next, clips.length, skip]);

    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => void (document.body.style.overflow = '');
    }, []);

    const remove = async (clip: ClipItem) => {
        await fetch(route('posts.destroy', clip.id), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
        setClips((list) => list.filter((c) => c.id !== clip.id));
    };

    return (
        <div className="fixed inset-0 z-40 bg-black text-white" dir={dir}>
            <Head title={t('Clips')} />
            <div className="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-center justify-between bg-gradient-to-b from-black/60 to-transparent px-3 pt-[max(10px,env(safe-area-inset-top))] pb-6">
                <Link
                    href={route('dashboard')}
                    className="pointer-events-auto inline-flex items-center gap-2 rounded-full p-2 text-lg font-bold hover:bg-white/15"
                >
                    <ArrowLeft className="size-6 rtl:-scale-x-100" /> {t('Clips')}
                </Link>
                <Link
                    href={route('dashboard', { clip: 1 })}
                    className="pointer-events-auto inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold hover:bg-white/25"
                >
                    <Plus className="size-4" /> {t('Make a clip')}
                </Link>
            </div>

            {clips.length === 0 ? (
                <div className="flex h-full flex-col items-center justify-center gap-4 p-6 text-center">
                    <p className="text-lg">{t('No clips yet.')}</p>
                    <Link href={route('dashboard', { clip: 1 })} className="bg-brand rounded-full px-5 py-2.5 font-semibold">
                        {t('Make the first clip')}
                    </Link>
                </div>
            ) : (
                <div ref={scroller} className="h-full snap-y snap-mandatory overflow-y-scroll [scrollbar-width:none]">
                    {clips.map((clip, i) => (
                        <ClipSlide
                            key={clip.id}
                            index={i}
                            clip={clip}
                            active={i === current}
                            near={Math.abs(i - current) <= 1}
                            muted={muted}
                            onMute={setMuted}
                            translated={translations('post', clip.id)?.body ?? clip.translation?.fields?.body}
                            onDelete={() => remove(clip)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function ClipSlide({
    clip,
    index,
    active,
    near,
    muted,
    onMute,
    translated,
    onDelete,
}: {
    clip: ClipItem;
    index: number;
    active: boolean;
    near: boolean;
    muted: boolean;
    onMute: (muted: boolean) => void;
    translated?: string;
    onDelete: () => void;
}) {
    const t = useT();
    const video = useRef<HTMLVideoElement>(null);
    const [paused, setPaused] = useState(saveData);
    const [liked, setLiked] = useState(clip.my_reaction !== null);
    const [likes, setLikes] = useState(clip.likes_count);
    const [original, setOriginal] = useState(false);
    const [open, setOpen] = useState(false);
    const [notice, setNotice] = useState('');

    useEffect(() => {
        const el = video.current;
        if (!el) return;
        if (active && !paused) {
            el.play().catch(() => undefined);
        } else {
            el.pause();
        }
        if (!active) {
            el.currentTime = 0;
            setPaused(saveData());
        }
    }, [active, paused, near]);

    // A view counts after two seconds on screen.
    useEffect(() => {
        if (!active) return;
        const timer = window.setTimeout(
            () => fetch(route('clips.view', clip.id), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } }),
            2000,
        );
        return () => window.clearTimeout(timer);
    }, [active, clip.id]);

    const like = () => {
        setLiked(!liked);
        setLikes(likes + (liked ? -1 : 1));
        router.post(route('posts.like', clip.id), {}, { preserveScroll: true, preserveState: true, only: ['__none'] });
    };

    const share = async () => {
        const nav: Partial<Navigator> = navigator;
        if (nav.share) return nav.share({ url: clip.url }).catch(() => undefined);
        await nav.clipboard?.writeText(clip.url);
        setNotice(t('Link copied'));
        window.setTimeout(() => setNotice(''), 1800);
    };

    const body = !original && translated ? translated : clip.body;
    const portrait = !clip.video.width || !clip.video.height || clip.video.height >= clip.video.width;
    const action = 'flex flex-col items-center gap-1 text-xs font-semibold drop-shadow';

    return (
        <section data-index={index} className="relative flex h-[100dvh] snap-start snap-always items-center justify-center">
            <div className="relative h-full w-full sm:aspect-[9/16] sm:w-auto">
                <div className="absolute inset-0" onClick={() => setPaused(!paused)}>
                    {near ? (
                        <video
                            ref={video}
                            src={clip.video.file_url ?? undefined}
                            poster={clip.video.thumb ?? undefined}
                            muted={muted}
                            loop
                            playsInline
                            preload={active ? 'auto' : 'metadata'}
                            className={`h-full w-full ${portrait ? 'object-cover' : 'object-contain'}`}
                        />
                    ) : (
                        clip.video.thumb && <img src={clip.video.thumb} alt="" loading="lazy" className="h-full w-full object-cover" />
                    )}
                    {paused && (
                        <span className="absolute inset-0 flex items-center justify-center">
                            <span className="flex size-16 items-center justify-center rounded-full bg-black/50">
                                <Play className="ms-1 size-8 fill-current" />
                            </span>
                        </span>
                    )}
                </div>

                {notice && (
                    <div className="absolute inset-x-0 top-1/3 flex justify-center">
                        <span className="rounded-full bg-black/70 px-4 py-2 text-sm">{notice}</span>
                    </div>
                )}

                <div className="absolute end-2 bottom-28 flex flex-col items-center gap-5">
                    <button type="button" onClick={like} className={action} aria-label={t('Like')}>
                        <Heart className={`size-8 ${liked ? 'fill-rose-500 text-rose-500' : ''}`} />
                        {likes}
                    </button>
                    <Link href={clip.url} className={action} aria-label={t('Comments')}>
                        <MessageCircle className="size-8 rtl:-scale-x-100" />
                        {clip.comments_count}
                    </Link>
                    <button type="button" onClick={share} className={action} aria-label={t('Share')}>
                        <Share2 className="size-7" />
                    </button>
                    <button type="button" onClick={() => onMute(!muted)} className={action} aria-label={muted ? t('Sound on') : t('Sound off')}>
                        {muted ? <VolumeX className="size-7" /> : <Volume2 className="size-7" />}
                    </button>
                    {clip.can_delete && (
                        <button type="button" onClick={onDelete} className={action} aria-label={t('Delete')}>
                            <Trash2 className="size-6" />
                        </button>
                    )}
                </div>

                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent ps-3 pe-16 pt-10 pb-[max(14px,env(safe-area-inset-bottom))]">
                    {clip.author && (
                        <Link href={route('people.show', clip.author.id)} className="mb-2 inline-flex items-center gap-2">
                            <Avatar className="size-9 border border-white/50">
                                <AvatarImage src={clip.author.avatar ?? undefined} alt="" className="object-cover" />
                                <DefaultAvatarFallback />
                            </Avatar>
                            <span dir="auto" className="font-semibold drop-shadow">
                                {clip.author.name}
                            </span>
                        </Link>
                    )}
                    {body && (
                        <div
                            dir="auto"
                            onClick={() => setOpen(!open)}
                            className={`text-[15px] leading-snug drop-shadow [&_a]:underline ${open ? 'max-h-48 overflow-y-auto' : 'line-clamp-2'}`}
                            dangerouslySetInnerHTML={{ __html: body }}
                        />
                    )}
                    <div className="mt-1.5 flex items-center gap-3 text-xs text-white/75">
                        <span className="inline-flex items-center gap-1">
                            <Eye className="size-3.5" /> {clip.views}
                        </span>
                        {translated && (
                            <button type="button" onClick={() => setOriginal(!original)} className="underline">
                                {original ? t('Show translation') : t('Show original')}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
