import { useT } from '@/lib/i18n';
import { type PhotoItem } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ExternalLink, ThumbsUp, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

interface Props {
    photos: PhotoItem[];
    index: number | null;
    onClose: () => void;
    /** Lets the owner delete pictures and write captions. */
    editable?: boolean;
    likeable?: boolean;
}

/** Full-screen picture viewer with arrows, swipe-free keyboard control and an optional caption editor. */
export function PhotoViewer({ photos, index, onClose, editable = false, likeable = false }: Props) {
    const t = useT();
    const [current, setCurrent] = useState(index ?? 0);
    const [caption, setCaption] = useState('');
    const photo = index === null ? null : photos[current];

    useEffect(() => setCurrent(index ?? 0), [index]);
    useEffect(() => setCaption(photo?.caption ?? ''), [photo?.id]);

    useEffect(() => {
        if (index === null) return;
        const onKey = (e: KeyboardEvent) => {
            if ((e.target as HTMLElement).tagName === 'INPUT') return;
            if (e.key === 'Escape') onClose();
            const rtl = document.documentElement.dir === 'rtl';
            if (e.key === 'ArrowRight') step(rtl ? -1 : 1);
            if (e.key === 'ArrowLeft') step(rtl ? 1 : -1);
        };
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [index, photos.length]);

    if (!photo) return null;

    function step(by: number) {
        setCurrent((i) => (i + by + photos.length) % photos.length);
    }

    const remove = () => {
        if (!window.confirm(t('Delete this photo?'))) return;
        router.delete(route('photos.destroy', photo.id), {
            preserveScroll: true,
            onSuccess: () => (photos.length <= 1 ? onClose() : step(current === photos.length - 1 ? -1 : 0)),
        });
    };

    const arrow = 'absolute top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/30';

    return createPortal(
        <div className="fixed inset-0 z-50 flex flex-col bg-black/95" onClick={onClose}>
            <div className="flex items-center justify-between gap-2 p-3 text-white" onClick={(e) => e.stopPropagation()}>
                <span className="text-sm opacity-70">
                    {current + 1} / {photos.length}
                </span>
                <div className="flex items-center gap-1">
                    {likeable && (
                        <button
                            type="button"
                            className={`flex items-center gap-1.5 rounded-full px-3 py-2 text-sm hover:bg-white/15 ${photo.liked ? 'text-sky-300' : ''}`}
                            onClick={() => router.post(route('photos.like', photo.id), {}, { preserveScroll: true, preserveState: true })}
                        >
                            <ThumbsUp className={`size-5 ${photo.liked ? 'fill-current' : ''}`} /> {photo.likes_count || ''}
                        </button>
                    )}
                    {photo.post_url && (
                        <Link href={photo.post_url} className="rounded-full p-2 hover:bg-white/15" aria-label={t('Open post')} title={t('Open post')}>
                            <ExternalLink className="size-5" />
                        </Link>
                    )}
                    {editable && (
                        <button type="button" onClick={remove} className="rounded-full p-2 hover:bg-white/15" aria-label={t('Delete')}>
                            <Trash2 className="size-5" />
                        </button>
                    )}
                    <button type="button" onClick={onClose} className="rounded-full p-2 hover:bg-white/15" aria-label={t('Close')}>
                        <X className="size-6" />
                    </button>
                </div>
            </div>
            <div className="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-16">
                <img
                    src={photo.url}
                    alt={photo.caption ?? ''}
                    className="max-h-full max-w-full object-contain"
                    onClick={(e) => e.stopPropagation()}
                />
                {photos.length > 1 && (
                    <>
                        <button
                            type="button"
                            className={`${arrow} start-2`}
                            onClick={(e) => (e.stopPropagation(), step(-1))}
                            aria-label={t('Previous')}
                        >
                            <ChevronLeft className="size-7 rtl:rotate-180" />
                        </button>
                        <button type="button" className={`${arrow} end-2`} onClick={(e) => (e.stopPropagation(), step(1))} aria-label={t('Next')}>
                            <ChevronRight className="size-7 rtl:rotate-180" />
                        </button>
                    </>
                )}
            </div>
            <div className="p-3 text-center text-white" onClick={(e) => e.stopPropagation()}>
                {editable ? (
                    <form
                        className="mx-auto flex max-w-xl gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.patch(
                                route('photos.update', photo.id),
                                { caption: caption || null },
                                { preserveScroll: true, preserveState: true },
                            );
                        }}
                    >
                        <input
                            dir="auto"
                            value={caption}
                            onChange={(e) => setCaption(e.target.value)}
                            placeholder={t('Add a caption…')}
                            className="flex-1 rounded-md bg-white/10 px-3 py-2 text-sm outline-none placeholder:text-white/50"
                        />
                        {caption !== (photo.caption ?? '') && <button className="bg-brand rounded-md px-4 text-sm font-semibold">{t('Save')}</button>}
                    </form>
                ) : (
                    photo.caption && (
                        <p dir="auto" className="text-sm">
                            {photo.caption}
                        </p>
                    )
                )}
            </div>
        </div>,
        document.body,
    );
}
