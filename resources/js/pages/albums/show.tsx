import { AlbumForm } from '@/components/album-form';
import { PhotoViewer } from '@/components/photo-viewer';
import { UserAvatar } from '@/components/user-avatar';
import ClubLayout from '@/layouts/club-layout';
import { albumName } from '@/lib/albums';
import { useT } from '@/lib/i18n';
import { shrinkImage } from '@/lib/shrink-image';
import { type Person, type PhotoItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ImagePlus, Pencil, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

interface Props {
    album: {
        id: number;
        name: string;
        description: string | null;
        kind: string | null;
        owner: Person | null;
        mine: boolean;
        created_at: string | null;
    };
    photos: PhotoItem[];
    open: number | null;
}

const MAX_PHOTOS = 20;
const secondary =
    'inline-flex items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700';

export default function AlbumShow({ album, photos, open }: Props) {
    const t = useT();
    const [viewing, setViewing] = useState<number | null>(() => {
        const i = photos.findIndex((p) => p.id === open);
        return i >= 0 ? i : null;
    });
    const [editing, setEditing] = useState(false);
    const [share, setShare] = useState(true);
    const [progress, setProgress] = useState<number | null>(null);
    const picker = useRef<HTMLInputElement>(null);
    const name = albumName(album, t);

    const upload = async (files: FileList | null) => {
        if (!files?.length) return;
        const all = Array.from(files);
        setProgress(0);
        // Sent in batches so a big selection does not run into the server's upload limit.
        for (let i = 0; i < all.length; i += MAX_PHOTOS) {
            const images = await Promise.all(all.slice(i, i + MAX_PHOTOS).map((file) => shrinkImage(file)));
            await new Promise<void>((resolve) =>
                router.post(
                    route('albums.upload', album.id),
                    { images, share: share && i === 0 },
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        onProgress: (e) => setProgress(Math.round(((i + (e?.percentage ?? 0) / 100) / all.length) * 100)),
                        onFinish: () => resolve(),
                    },
                ),
            );
        }
        setProgress(null);
        if (picker.current) picker.current.value = '';
    };

    const remove = () => {
        if (window.confirm(t('Delete the album and all its photos?'))) router.delete(route('albums.destroy', album.id));
    };

    return (
        <ClubLayout>
            <Head title={name} />
            <div className="mx-auto w-full max-w-[1100px] space-y-4">
                <section className="rounded-lg bg-white p-5 shadow-sm dark:bg-neutral-900">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <h1 dir="auto" className="text-2xl font-bold">
                                {name}
                            </h1>
                            {album.owner && (
                                <Link
                                    href={route('people.show', { user: album.owner.id, tab: 'photos' })}
                                    className="text-muted-foreground mt-1 inline-flex items-center gap-2 text-[15px] hover:underline"
                                >
                                    <UserAvatar person={album.owner} className="size-6" fallbackClassName="text-[10px]" /> {album.owner.name} ·{' '}
                                    {t(':count photos', { count: photos.length })}
                                </Link>
                            )}
                            {album.description && (
                                <p dir="auto" className="mt-2 text-[15px] whitespace-pre-line">
                                    {album.description}
                                </p>
                            )}
                        </div>
                        {album.mine && (
                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    ref={picker}
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => upload(e.target.files)}
                                />
                                <button
                                    type="button"
                                    className="bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                                    disabled={progress !== null}
                                    onClick={() => picker.current?.click()}
                                >
                                    <ImagePlus className="size-4" /> {progress !== null ? `${progress}%` : t('Add photos')}
                                </button>
                                {!album.kind && (
                                    <>
                                        <button type="button" className={secondary} onClick={() => setEditing(true)} aria-label={t('Edit')}>
                                            <Pencil className="size-4" />
                                        </button>
                                        <button type="button" className={secondary} onClick={remove} aria-label={t('Delete')}>
                                            <Trash2 className="size-4" />
                                        </button>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                    {album.mine && (
                        <label className="text-muted-foreground mt-3 flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={share} onChange={(e) => setShare(e.target.checked)} className="accent-brand size-4" />
                            {t('Also show new photos in the feed')}
                        </label>
                    )}
                </section>

                <section className="rounded-lg bg-white p-2 shadow-sm dark:bg-neutral-900">
                    {photos.length === 0 ? (
                        <p className="text-muted-foreground p-6 text-center text-[15px]">{t('No photos yet.')}</p>
                    ) : (
                        <div className="grid grid-cols-3 gap-1 sm:grid-cols-4 lg:grid-cols-5">
                            {photos.map((photo, i) => (
                                <button key={photo.id} type="button" onClick={() => setViewing(i)} className="block overflow-hidden rounded">
                                    <img
                                        src={photo.thumb}
                                        alt={photo.caption ?? ''}
                                        loading="lazy"
                                        className="aspect-square w-full object-cover transition hover:opacity-90"
                                    />
                                </button>
                            ))}
                        </div>
                    )}
                </section>
            </div>

            <PhotoViewer photos={photos} index={viewing} onClose={() => setViewing(null)} editable={album.mine} likeable />
            {album.mine && <AlbumForm album={album} open={editing} onClose={() => setEditing(false)} />}
        </ClubLayout>
    );
}
