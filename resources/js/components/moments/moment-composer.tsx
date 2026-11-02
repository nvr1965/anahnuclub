import { CameraCapture, cameraAvailable } from '@/components/camera-capture';
import { useT } from '@/lib/i18n';
import { shrinkImage, videoFrame, videoInfo } from '@/lib/shrink-image';
import { type MomentItem, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { AlignVerticalJustifyCenter, ArrowDownToLine, ArrowUpToLine, Camera, ImagePlus, Type, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { BACKGROUNDS, MOMENT_SECONDS, MomentWords } from './moment-look';

type Kind = MomentItem['kind'];

/** Largest video of a moment: 30 seconds recorded here are about 6 MB. */
const MAX_VIDEO_BYTES = 20 * 1024 * 1024;

/**
 * Making a moment: a photo or a video of up to 30 seconds (from the camera or the gallery),
 * or just words on a colour; words can go over a picture too. Then who sees it, and it is up for a day.
 */
export default function MomentComposer({ lists, onClose }: { lists: { id: number; name: string }[]; onClose: () => void }) {
    const t = useT();
    const { dir } = usePage<SharedData>().props;
    const [step, setStep] = useState<'pick' | 'camera' | 'edit'>('pick');
    const [kind, setKind] = useState<Kind>('text');
    const [file, setFile] = useState<File | null>(null);
    const [thumb, setThumb] = useState<File | null>(null);
    const [duration, setDuration] = useState<number | null>(null);
    const [text, setText] = useState('');
    const [position, setPosition] = useState<MomentItem['text_position']>('bottom');
    const [background, setBackground] = useState<string | null>(null);
    const [audience, setAudience] = useState('friends');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [percent, setPercent] = useState<number | null>(null);
    const gallery = useRef<HTMLInputElement>(null);
    const nativeCamera = useRef<HTMLInputElement>(null);

    const preview = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);
    useEffect(() => () => void (preview && URL.revokeObjectURL(preview)), [preview]);

    useEffect(() => {
        document.body.style.overflow = 'hidden';
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && step !== 'camera' && onClose();
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKey);
        };
    }, [onClose, step]);

    const take = async (picked: File | null | undefined) => {
        if (gallery.current) gallery.current.value = '';
        if (nativeCamera.current) nativeCamera.current.value = '';
        if (!picked) return;
        setError('');
        setBusy(true);
        if (picked.type.startsWith('video/')) {
            const info = await videoInfo(picked);
            if (info && info.duration > MOMENT_SECONDS + 1) {
                setBusy(false);
                setStep('pick');
                return setError(t('A moment video can be up to :n seconds.', { n: MOMENT_SECONDS }));
            }
            if (picked.size > MAX_VIDEO_BYTES) {
                setBusy(false);
                setStep('pick');
                return setError(t('The video is too large for a moment (up to 20 MB).'));
            }
            setKind('video');
            setFile(picked);
            setThumb(await videoFrame(picked));
            setDuration(info?.duration ?? null);
        } else {
            setKind('photo');
            setFile(await shrinkImage(picked, 1920));
            setThumb(null);
            setDuration(null);
        }
        setBackground(null);
        setPosition('bottom');
        setBusy(false);
        setStep('edit');
    };

    const words = () => {
        setKind('text');
        setFile(null);
        setBackground('brand');
        setPosition('center');
        setStep('edit');
    };

    const publish = () => {
        setBusy(true);
        setError('');
        const list = audience.startsWith('list:') ? Number(audience.slice(5)) : null;
        router.post(
            route('moments.store'),
            {
                kind,
                photo: kind === 'photo' ? file : null,
                video: kind === 'video' ? file : null,
                thumb: kind === 'video' ? thumb : null,
                duration,
                text,
                text_position: position,
                background,
                audience: list ? 'list' : audience,
                friend_list_id: list,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                only: ['moments'],
                onProgress: (p) => setPercent(p?.percentage ?? null),
                onSuccess: () => onClose(),
                onError: (errors) => setError(Object.values(errors)[0] ?? t('Could not publish.')),
                onFinish: () => (setBusy(false), setPercent(null)),
            },
        );
    };

    const ready = kind === 'text' ? text.trim() !== '' : file !== null;
    const swatch = (key: string | null) => (
        <button
            key={key ?? 'none'}
            type="button"
            onClick={() => setBackground(key)}
            aria-label={key ?? t('No background')}
            className={`size-8 shrink-0 rounded-full border-2 ${background === key ? 'border-white' : 'border-white/30'}`}
            style={{ background: key ? BACKGROUNDS[key] : 'rgba(0,0,0,.55)' }}
        />
    );

    return (
        <div
            className="fixed inset-0 z-[70] flex flex-col bg-neutral-950 text-white"
            dir={dir}
            role="dialog"
            aria-modal="true"
            aria-label={t('New moment')}
        >
            <input ref={gallery} type="file" accept="image/*,video/*" className="hidden" onChange={(e) => take(e.target.files?.[0])} />
            <input
                ref={nativeCamera}
                type="file"
                accept="image/*,video/*"
                capture="environment"
                className="hidden"
                onChange={(e) => take(e.target.files?.[0])}
            />

            <div className="flex items-center justify-between px-3 pt-[max(10px,env(safe-area-inset-top))] pb-2">
                <h2 className="text-lg font-semibold">{t('New moment')}</h2>
                <button type="button" onClick={onClose} className="rounded-full p-2 hover:bg-white/15" aria-label={t('Close')}>
                    <X className="size-6" />
                </button>
            </div>

            {step === 'camera' && (
                <CameraCapture
                    vertical
                    maxSeconds={MOMENT_SECONDS}
                    onClose={() => setStep('pick')}
                    onPhoto={take}
                    onVideo={take}
                    onPick={() => gallery.current?.click()}
                />
            )}

            {step !== 'edit' ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6">
                    {busy ? (
                        <span className="size-8 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                    ) : (
                        <div className="grid w-full max-w-sm grid-cols-3 gap-3">
                            <button
                                type="button"
                                onClick={() => (cameraAvailable() ? setStep('camera') : nativeCamera.current?.click())}
                                className="flex aspect-[3/4] flex-col items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-rose-500 to-fuchsia-600 font-semibold"
                            >
                                <Camera className="size-8" /> {t('Camera')}
                            </button>
                            <button
                                type="button"
                                onClick={() => gallery.current?.click()}
                                className="flex aspect-[3/4] flex-col items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 font-semibold"
                            >
                                <ImagePlus className="size-8" /> {t('Gallery')}
                            </button>
                            <button
                                type="button"
                                onClick={words}
                                className="flex aspect-[3/4] flex-col items-center justify-center gap-2 rounded-2xl font-semibold"
                                style={{ background: BACKGROUNDS.brand }}
                            >
                                <Type className="size-8" /> {t('Text')}
                            </button>
                        </div>
                    )}
                    <p className="max-w-sm text-center text-sm text-white/70">{t('A moment is seen for 24 hours, then it disappears.')}</p>
                    {error && <p className="max-w-sm text-center text-sm text-red-400">{error}</p>}
                </div>
            ) : (
                <div className="flex min-h-0 flex-1 flex-col items-center gap-3 overflow-y-auto px-3 pb-[max(12px,env(safe-area-inset-bottom))] sm:flex-row sm:items-start sm:justify-center">
                    {/* As friends will see it. */}
                    <div className="relative aspect-[9/16] h-[min(52dvh,560px)] shrink-0 overflow-hidden rounded-xl bg-black sm:h-[min(80dvh,720px)]">
                        {kind === 'photo' && preview && <img src={preview} alt="" className="h-full w-full object-contain" />}
                        {kind === 'video' && preview && (
                            <video src={preview} autoPlay loop muted playsInline className="h-full w-full object-contain" />
                        )}
                        {kind === 'text' ? (
                            <MomentWords text={text || t('Type something…')} kind="text" position="center" background={background} />
                        ) : (
                            text && <MomentWords text={text} kind={kind} position={position} background={background} />
                        )}
                    </div>

                    <div className="w-full max-w-sm space-y-3">
                        <textarea
                            dir="auto"
                            autoFocus={kind === 'text'}
                            rows={kind === 'text' ? 4 : 2}
                            maxLength={500}
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            placeholder={kind === 'text' ? t('Type something…') : t('Add words on top (optional)')}
                            className="w-full resize-none rounded-xl bg-white/10 px-3 py-2 text-[0.9375rem] outline-none placeholder:text-white/50"
                        />
                        <div className="flex items-center gap-2 overflow-x-auto">
                            {kind !== 'text' && swatch(null)}
                            {Object.keys(BACKGROUNDS).map(swatch)}
                        </div>
                        {kind !== 'text' && (
                            <div className="flex gap-2">
                                {(
                                    [
                                        ['top', ArrowUpToLine, t('Top')],
                                        ['center', AlignVerticalJustifyCenter, t('Middle')],
                                        ['bottom', ArrowDownToLine, t('Bottom')],
                                    ] as const
                                ).map(([key, Icon, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => setPosition(key)}
                                        className={`inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-sm ${position === key ? 'bg-white text-black' : 'bg-white/10'}`}
                                    >
                                        <Icon className="size-4" /> {label}
                                    </button>
                                ))}
                            </div>
                        )}
                        <label className="block text-sm">
                            <span className="mb-1 block text-white/70">{t('Who can see it')}</span>
                            <select
                                value={audience}
                                onChange={(e) => setAudience(e.target.value)}
                                className="w-full rounded-lg bg-white/10 px-3 py-2 text-[0.9375rem] outline-none [&>option]:text-black"
                            >
                                <option value="friends">{t('Friends')}</option>
                                <option value="members">{t('All members')}</option>
                                {lists.map((list) => (
                                    <option key={list.id} value={`list:${list.id}`}>
                                        {list.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {error && <p className="text-sm text-red-400">{error}</p>}
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => (setStep('pick'), setFile(null), setText(''))}
                                className="rounded-lg bg-white/10 px-4 py-2.5 font-semibold"
                            >
                                {t('Back')}
                            </button>
                            <button
                                type="button"
                                disabled={!ready || busy}
                                onClick={publish}
                                className="bg-brand hover:bg-brand-dark flex-1 rounded-lg px-4 py-2.5 font-semibold disabled:opacity-40"
                            >
                                {busy ? (percent !== null ? `${percent}%` : t('Publishing…')) : t('Share the moment')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
