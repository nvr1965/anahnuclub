import { PhotoViewer } from '@/components/photo-viewer';
import { coverStyle, defaultImages } from '@/lib/default-images';
import { useT } from '@/lib/i18n';
import { type EventItem, type PhotoItem, type SharedData, type VideoItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, MapPin, Play } from 'lucide-react';
import { useState } from 'react';

/** Pictures in a post, laid out like Facebook: one big, two side by side, or a grid with "+N". */
export function PostPhotos({ photos }: { photos: PhotoItem[] }) {
    const [open, setOpen] = useState<number | null>(null);
    if (photos.length === 0) return null;
    const shown = photos.slice(0, 4);
    const extra = photos.length - shown.length;
    const layout = photos.length === 1 ? 'grid-cols-1' : photos.length === 3 ? 'grid-cols-2 grid-rows-2' : 'grid-cols-2';

    return (
        <>
            <div className={`grid gap-0.5 ${layout}`}>
                {shown.map((photo, i) => (
                    <button
                        key={photo.id}
                        type="button"
                        onClick={() => setOpen(i)}
                        className={`relative block overflow-hidden bg-slate-100 dark:bg-neutral-800 ${photos.length === 3 && i === 0 ? 'row-span-2' : ''}`}
                    >
                        <img
                            src={photos.length === 1 ? photo.url : photo.thumb}
                            alt={photo.caption ?? ''}
                            loading="lazy"
                            className={`w-full object-cover ${photos.length === 1 ? 'max-h-[600px]' : 'aspect-square h-full'}`}
                        />
                        {extra > 0 && i === shown.length - 1 && (
                            <span className="absolute inset-0 flex items-center justify-center bg-black/50 text-3xl font-semibold text-white">
                                +{extra}
                            </span>
                        )}
                    </button>
                ))}
            </div>
            <PhotoViewer photos={photos} index={open} onClose={() => setOpen(null)} />
        </>
    );
}

/** A video player: YouTube or Vimeo in a frame, uploaded files in the browser's own player. */
export function VideoPlayer({ video, autoPlay = false }: { video: VideoItem; autoPlay?: boolean }) {
    const [playing, setPlaying] = useState(autoPlay);

    if (video.provider === 'file') {
        return (
            <video
                src={video.file_url ?? undefined}
                poster={video.thumb ?? undefined}
                controls
                preload="metadata"
                autoPlay={autoPlay}
                className="aspect-video w-full bg-black"
            />
        );
    }

    // The frame loads only when asked, so a feed full of videos stays light.
    if (!playing) {
        return (
            <button type="button" onClick={() => setPlaying(true)} className="group relative block aspect-video w-full overflow-hidden bg-black">
                {video.thumb && (
                    <img
                        src={video.thumb}
                        alt=""
                        loading="lazy"
                        onError={(e) => (e.currentTarget.style.display = 'none')}
                        className="size-full object-cover opacity-90 transition group-hover:opacity-100"
                    />
                )}
                <span className="absolute inset-0 flex items-center justify-center">
                    <span className="flex size-16 items-center justify-center rounded-full bg-black/60 text-white transition group-hover:scale-110">
                        <Play className="ms-1 size-8 fill-current" />
                    </span>
                </span>
            </button>
        );
    }

    return (
        <iframe
            src={`${video.embed_url}?autoplay=1`}
            title={video.title}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
            allowFullScreen
            className="aspect-video w-full bg-black"
        />
    );
}

export function PostVideo({ video }: { video: VideoItem }) {
    return (
        <div>
            <VideoPlayer video={video} />
            <Link
                href={route('videos.show', video.id)}
                dir="auto"
                className="block bg-slate-50 px-4 py-2.5 text-[15px] font-semibold hover:underline dark:bg-neutral-800/60"
            >
                {video.title}
            </Link>
        </div>
    );
}

export function eventDate(iso: string | null, locale: string, withTime = true) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(locale, {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

/** An event as it appears in a post and in lists. */
export function EventCard({ event }: { event: EventItem }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const start = event.starts_at ? new Date(event.starts_at) : null;

    return (
        <Link
            href={route('events.show', event.id)}
            className="block overflow-hidden bg-slate-50 hover:bg-slate-100 dark:bg-neutral-800/60 dark:hover:bg-neutral-800"
        >
            <div
                className="from-brand to-header-to aspect-[1640/624] bg-gradient-to-br bg-cover bg-center"
                style={coverStyle(event.cover, defaultImages.eventCover)}
            />
            <div className="flex gap-3 px-4 py-3">
                {start && (
                    <div className="w-12 shrink-0 overflow-hidden rounded-lg bg-white text-center shadow-sm dark:bg-neutral-900">
                        <div className="bg-red-500 text-[11px] font-semibold text-white uppercase">
                            {start.toLocaleString(locale, { month: 'short' })}
                        </div>
                        <div className="text-xl font-bold">{start.getDate()}</div>
                    </div>
                )}
                <div className="min-w-0 text-[15px]">
                    <div className="text-[13px] font-semibold text-red-600 uppercase dark:text-red-400">{eventDate(event.starts_at, locale)}</div>
                    <div dir="auto" className="truncate font-semibold">
                        {event.title}
                    </div>
                    <div className="text-muted-foreground flex flex-wrap gap-x-3 text-[13px]">
                        {event.location && (
                            <span className="inline-flex items-center gap-1" dir="auto">
                                <MapPin className="size-3.5" /> {event.location}
                            </span>
                        )}
                        <span className="inline-flex items-center gap-1">
                            <CalendarDays className="size-3.5" /> {t(':count going', { count: event.going_count })}
                        </span>
                    </div>
                </div>
            </div>
        </Link>
    );
}
