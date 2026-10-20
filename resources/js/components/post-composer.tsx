import { AudienceIcon, AudiencePicker, useAudienceLabels } from '@/components/audience-picker';
import { CameraCapture, cameraAvailable } from '@/components/camera-capture';
import { LinkPreview } from '@/components/link-preview';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { shrinkImage, videoFrame } from '@/lib/shrink-image';
import { type Audience, type LinkCard, type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Camera, ChevronDown, ImagePlus, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

const MAX_PHOTOS = 20;

/** "What's on your mind?" box: a pill that opens into a text area, with pictures or a video, picked or shot with the camera. */
export function PostComposer({ groupId }: { groupId?: number }) {
    const t = useT();
    const getInitials = useInitials();
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [preparing, setPreparing] = useState(false);
    const [shooting, setShooting] = useState(false);
    const [choosingAudience, setChoosingAudience] = useState(false);
    const audienceLabels = useAudienceLabels();
    const picker = useRef<HTMLInputElement>(null);
    const nativeCamera = useRef<HTMLInputElement>(null);
    const form = useForm<{
        body: string;
        group_id: number | null;
        images: File[];
        video: File | null;
        video_thumb: File | null;
        audience: Audience;
        audience_ids: number[];
        audience_default: boolean;
        no_link_preview: boolean;
    }>({
        body: '',
        group_id: groupId ?? null,
        images: [],
        video: null,
        video_thumb: null,
        audience: auth.user?.default_audience ?? 'public',
        audience_ids: [],
        audience_default: false,
        no_link_preview: false,
    });
    // The first link typed shows as a card, as on Facebook; closing it keeps the link as text only.
    const [link, setLink] = useState<LinkCard | null>(null);
    const [dismissed, setDismissed] = useState<string | null>(null);
    const typedUrl = form.data.body.match(/https?:\/\/[^\s<>"']+/i)?.[0].replace(/[.,;:!?)\]}»]+$/, '') ?? null;
    useEffect(() => {
        if (!typedUrl || typedUrl === dismissed) return setLink(null);
        if (link?.url === typedUrl) return;
        const ask = new AbortController();
        const wait = setTimeout(async () => {
            try {
                const response = await fetch(route('links.preview', { url: typedUrl }), {
                    headers: { Accept: 'application/json' },
                    signal: ask.signal,
                });
                setLink(response.ok ? ((await response.json()).link ?? null) : null);
            } catch {
                // A page that cannot be read leaves just the link.
            }
        }, 600);
        return () => (clearTimeout(wait), ask.abort());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [typedUrl, dismissed]);
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const previews = useMemo(() => form.data.images.map((file) => URL.createObjectURL(file)), [form.data.images]);

    const videoPreview = useMemo(() => (form.data.video ? URL.createObjectURL(form.data.video) : null), [form.data.video]);

    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);
    useEffect(() => () => void (videoPreview && URL.revokeObjectURL(videoPreview)), [videoPreview]);

    /** Pictures join the post; a video (one per post) takes the video slot with a still for lists. */
    const addFiles = async (files: FileList | File[] | null) => {
        const all = Array.from(files ?? []);
        if (!all.length) return;
        setOpen(true);
        setPreparing(true);
        const video = all.find((file) => file.type.startsWith('video/'));
        const shrunk = await Promise.all(
            all
                .filter((file) => file.type.startsWith('image/'))
                .slice(0, MAX_PHOTOS)
                .map((file) => shrinkImage(file)),
        );
        const thumb = video ? await videoFrame(video) : null;
        form.setData((data) => ({
            ...data,
            images: [...data.images, ...shrunk].slice(0, MAX_PHOTOS),
            ...(video ? { video, video_thumb: thumb } : {}),
        }));
        setPreparing(false);
        if (picker.current) picker.current.value = '';
        if (nativeCamera.current) nativeCamera.current.value = '';
    };

    // In-page camera over HTTPS; elsewhere the phone's own camera app through a file field.
    const openCamera = () => (cameraAvailable() ? setShooting(true) : nativeCamera.current?.click());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, no_link_preview: typedUrl !== null && typedUrl === dismissed }));
        form.post(route('posts.store'), {
            preserveScroll: true,
            forceFormData: form.data.images.length > 0 || form.data.video !== null,
            onSuccess: () => {
                // The audience stays as chosen for the next post, as on Facebook.
                form.reset('body', 'images', 'video', 'video_thumb', 'audience_default');
                setLink(null);
                setDismissed(null);
                setOpen(false);
            },
        });
    };

    const empty = !form.data.body.trim() && form.data.images.length === 0 && !form.data.video;
    const tool =
        'text-muted-foreground inline-flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800';
    const photoButton = (
        <div className="flex">
            <button type="button" onClick={() => picker.current?.click()} className={tool} title={t('Photo/video')} aria-label={t('Photo/video')}>
                <ImagePlus className="size-5 text-green-600" /> <span className={open ? 'hidden sm:inline' : ''}>{t('Photo/video')}</span>
            </button>
            <button type="button" onClick={openCamera} className={tool} title={t('Camera')} aria-label={t('Camera')}>
                <Camera className="size-5 text-rose-500" /> <span className={open ? 'hidden sm:inline' : ''}>{t('Camera')}</span>
            </button>
        </div>
    );

    return (
        <form onSubmit={submit} className="rounded-lg bg-white px-4 py-3 shadow-sm dark:bg-neutral-900">
            <input ref={picker} type="file" accept="image/*,video/*" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
            <input
                ref={nativeCamera}
                type="file"
                accept="image/*,video/*"
                capture="environment"
                className="hidden"
                onChange={(e) => addFiles(e.target.files)}
            />
            {shooting && (
                <CameraCapture
                    onClose={() => setShooting(false)}
                    onPhoto={(file) => (setShooting(false), addFiles([file]))}
                    onVideo={(file) => (setShooting(false), addFiles([file]))}
                />
            )}
            <div className="flex items-start gap-2">
                <Avatar className="size-10">
                    <AvatarImage src={auth.user?.avatar} alt="" className="object-cover" />
                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(auth.user?.name ?? '')}</AvatarFallback>
                </Avatar>
                {open ? (
                    <textarea
                        dir="auto"
                        autoFocus
                        rows={form.data.images.length ? 2 : 4}
                        className="flex-1 resize-none rounded-xl bg-slate-100 px-4 py-2.5 text-[15px] outline-none dark:bg-neutral-800"
                        placeholder={t("What's on your mind, :name?", { name: firstName })}
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="text-muted-foreground flex-1 rounded-full bg-slate-100 px-4 py-2.5 text-start text-[15px] hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                    >
                        {t("What's on your mind, :name?", { name: firstName })}
                    </button>
                )}
            </div>

            {open && !groupId && (
                <div className="mt-2 ps-12">
                    <button
                        type="button"
                        onClick={() => setChoosingAudience(true)}
                        className="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                    >
                        <AudienceIcon audience={form.data.audience} />
                        {audienceLabels[form.data.audience].title}
                        {(form.data.audience === 'except' || form.data.audience === 'specific') && ` (${form.data.audience_ids.length})`}
                        <ChevronDown className="size-3.5" />
                    </button>
                    <AudiencePicker
                        open={choosingAudience}
                        onClose={() => setChoosingAudience(false)}
                        value={{ audience: form.data.audience, ids: form.data.audience_ids, makeDefault: form.data.audience_default }}
                        onDone={(choice) => {
                            form.setData((data) => ({
                                ...data,
                                audience: choice.audience,
                                audience_ids: choice.ids,
                                audience_default: choice.makeDefault,
                            }));
                            setChoosingAudience(false);
                        }}
                    />
                </div>
            )}

            {previews.length > 0 && (
                <div className="mt-3 grid grid-cols-4 gap-1.5 sm:grid-cols-5">
                    {previews.map((url, i) => (
                        <div key={url} className="relative">
                            <img src={url} alt="" className="aspect-square w-full rounded-lg object-cover" />
                            <button
                                type="button"
                                aria-label={t('Remove')}
                                onClick={() =>
                                    form.setData(
                                        'images',
                                        form.data.images.filter((_, j) => j !== i),
                                    )
                                }
                                className="absolute end-1 top-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
            {videoPreview && (
                <div className="relative mt-3">
                    <video src={videoPreview} controls playsInline className="max-h-80 w-full rounded-lg bg-black" />
                    <button
                        type="button"
                        aria-label={t('Remove')}
                        onClick={() => form.setData((data) => ({ ...data, video: null, video_thumb: null }))}
                        className="absolute end-2 top-2 rounded-full bg-black/60 p-1.5 text-white hover:bg-black/80"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            )}
            {link && previews.length === 0 && !videoPreview && (
                <div className="mt-3">
                    <LinkPreview link={link} onRemove={() => (setDismissed(link.url), setLink(null))} />
                </div>
            )}
            {form.errors.video && <p className="mt-2 text-sm text-red-600">{form.errors.video}</p>}
            {form.errors.images && <p className="mt-2 text-sm text-red-600">{form.errors.images}</p>}
            {Object.keys(form.errors).some((k) => k.startsWith('images.')) && (
                <p className="mt-2 text-sm text-red-600">{t('One of the pictures could not be used.')}</p>
            )}

            {open ? (
                <div className="mt-3 flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-neutral-800">
                    {photoButton}
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => (form.reset('body', 'images', 'video', 'video_thumb', 'audience_default'), setOpen(false))}
                            className="text-muted-foreground rounded-md px-3 py-2 text-sm font-semibold whitespace-nowrap hover:bg-slate-100 dark:hover:bg-neutral-800"
                        >
                            {t('Cancel')}
                        </button>
                        <button
                            disabled={form.processing || preparing || empty}
                            className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold whitespace-nowrap text-white disabled:opacity-40"
                        >
                            {form.processing ? `${form.progress?.percentage ?? 0}%` : t('Publish')}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="mt-2 flex border-t border-slate-200 pt-2 dark:border-neutral-800">{photoButton}</div>
            )}
        </form>
    );
}
