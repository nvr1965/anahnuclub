import { AudienceIcon, AudiencePicker, useAudienceLabels } from '@/components/audience-picker';
import { CameraCapture, cameraAvailable } from '@/components/camera-capture';
import { ExternalEmbed } from '@/components/external-embed';
import { LinkPreview } from '@/components/link-preview';
import { AddToPost, ExtrasChips, FeelingPicker, LifeEventPicker, PlacePicker, type PostExtras } from '@/components/post-extras';
import { TagFriendsPicker, TagTextarea, type TaggedFriend, WithFriends } from '@/components/post-tags';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useCreateRequest } from '@/lib/create';
import { useT } from '@/lib/i18n';
import { shrinkImage, videoFrame, videoInfo } from '@/lib/shrink-image';
import { type Audience, type EmbedCard, type LinkCard, type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Camera, ChevronDown, Film, ImagePlus, UserPlus, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

const MAX_PHOTOS = 20;

/** A clip: a vertical video of up to a minute and a half, shown in the feed and in Clips. */
const CLIP_SECONDS = 90;
const CLIP_BYTES = 50 * 1024 * 1024;

/** Reset after posting or cancelling. */
const MEDIA = [
    'body',
    'feeling',
    'place',
    'life_event',
    'images',
    'video',
    'video_thumb',
    'audience_default',
    'clip',
    'video_width',
    'video_height',
    'video_duration',
    'tagged_ids',
] as const;

/** "What's on your mind?" box: a pill that opens into a text area, with pictures or a video, picked or shot with the camera. */
export function PostComposer({
    groupId,
    initialBody,
    onPosted,
    onCancel,
}: {
    groupId?: number;
    initialBody?: string;
    onPosted?: () => void;
    onCancel?: () => void;
}) {
    const t = useT();
    const { auth, skin } = usePage<SharedData>().props;
    const [open, setOpen] = useState(!!initialBody);
    const [preparing, setPreparing] = useState(false);
    const [shooting, setShooting] = useState(false);
    // Opened from Clips with "Make a clip": straight to the camera.
    const [clipping, setClipping] = useState(
        () => !groupId && typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('clip'),
    );
    const [clipError, setClipError] = useState('');
    const clipPicker = useRef<HTMLInputElement>(null);
    const [choosingAudience, setChoosingAudience] = useState(false);
    // Friends the author is with, as on Facebook: "Anna — with Boris".
    const [tagging, setTagging] = useState(false);
    const [tagged, setTagged] = useState<TaggedFriend[]>([]);
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
        clip: boolean;
        video_width: number | null;
        video_height: number | null;
        video_duration: number | null;
        tagged_ids: number[];
        feeling: PostExtras['feeling'];
        place: string | null;
        life_event: PostExtras['life_event'];
    }>({
        body: initialBody ?? '',
        group_id: groupId ?? null,
        images: [],
        video: null,
        video_thumb: null,
        audience: auth.user?.default_audience ?? 'public',
        audience_ids: [],
        audience_default: false,
        no_link_preview: false,
        clip: false,
        video_width: null,
        video_height: null,
        video_duration: null,
        tagged_ids: [],
        feeling: null,
        place: null,
        life_event: null,
    });
    // "Add to your post": the window open for a feeling, a place or a life event.
    const [picking, setPicking] = useState<keyof PostExtras | null>(null);
    const gifPicker = useRef<HTMLInputElement>(null);
    // The first link typed shows as a card, as on Facebook; closing it keeps the link as text only.
    const [link, setLink] = useState<LinkCard | null>(null);
    // A post of Facebook, Instagram, YouTube… (its link or its "embed" code) shows as its player.
    const [embed, setEmbed] = useState<EmbedCard | null>(null);
    const [dismissed, setDismissed] = useState<string | null>(null);
    const typedUrl = form.data.body.match(/https?:\/\/[^\s<>"']+/i)?.[0].replace(/[.,;:!?)\]}»]+$/, '') ?? null;
    useEffect(() => {
        if (!typedUrl || typedUrl === dismissed) return setLink(null), setEmbed(null);
        if (link?.url === typedUrl) return;
        const ask = new AbortController();
        const wait = setTimeout(async () => {
            try {
                const response = await fetch(route('links.preview', { url: typedUrl }), {
                    headers: { Accept: 'application/json' },
                    signal: ask.signal,
                });
                const found = response.ok ? await response.json() : {};
                setLink(found.link ?? null);
                setEmbed(found.embed ?? null);
            } catch {
                // A page that cannot be read leaves just the link.
            }
        }, 600);
        return () => (clearTimeout(wait), ask.abort());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [typedUrl, dismissed]);
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const box = useRef<HTMLFormElement>(null);
    // "Post" and "Clip" in the "+" menu of the top bar, on the feed (not in a group).
    useCreateRequest('post', () => {
        if (groupId) return;
        setOpen(true);
        box.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => box.current?.querySelector('textarea')?.focus(), 50);
    });
    useCreateRequest('clip', () => !groupId && (cameraAvailable() ? setClipping(true) : clipPicker.current?.click()));
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
            ...(video ? { video, video_thumb: thumb, clip: false } : {}),
        }));
        setPreparing(false);
        if (picker.current) picker.current.value = '';
        if (nativeCamera.current) nativeCamera.current.value = '';
    };

    /** A clip from the camera or the gallery: checked for length here, before a long upload. */
    const addClip = async (file: File | null | undefined) => {
        if (clipPicker.current) clipPicker.current.value = '';
        setClipping(false);
        if (!file) return;
        setOpen(true);
        setClipError('');
        setPreparing(true);
        const info = await videoInfo(file);
        if (info && info.duration > CLIP_SECONDS + 1) {
            setPreparing(false);
            return setClipError(t('A clip can be up to :n seconds.', { n: CLIP_SECONDS }));
        }
        if (file.size > CLIP_BYTES) {
            setPreparing(false);
            return setClipError(t('A clip can be up to 50 MB.'));
        }
        const thumb = await videoFrame(file);
        form.setData((data) => ({
            ...data,
            video: file,
            video_thumb: thumb,
            clip: true,
            video_width: info?.width || null,
            video_height: info?.height || null,
            video_duration: info ? Math.round(info.duration * 10) / 10 : null,
        }));
        setPreparing(false);
    };

    // In-page camera over HTTPS; elsewhere the phone's own camera app through a file field.
    const openCamera = () => (cameraAvailable() ? setShooting(true) : nativeCamera.current?.click());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, no_link_preview: typedUrl !== null && typedUrl === dismissed }));
        form.post(route('posts.store'), {
            preserveScroll: true,
            // Shared from another site, the page stays to say it is done.
            preserveState: !!onPosted,
            forceFormData: form.data.images.length > 0 || form.data.video !== null,
            onSuccess: () => {
                // The audience stays as chosen for the next post, as on Facebook.
                form.reset(...MEDIA);
                setTagged([]);
                setLink(null);
                setEmbed(null);
                setDismissed(null);
                setOpen(false);
                onPosted?.();
            },
        });
    };

    const empty =
        !form.data.body.trim() &&
        form.data.images.length === 0 &&
        !form.data.video &&
        !form.data.feeling &&
        !form.data.place &&
        !form.data.life_event;
    const addToPost = (action: 'photo' | 'tag' | 'feeling' | 'place' | 'life_event' | 'gif' | 'camera' | 'clip') =>
        ({
            photo: () => picker.current?.click(),
            tag: () => setTagging(true),
            feeling: () => setPicking('feeling'),
            place: () => setPicking('place'),
            life_event: () => setPicking('life_event'),
            gif: () => gifPicker.current?.click(),
            camera: openCamera,
            clip: () => (cameraAvailable() ? setClipping(true) : clipPicker.current?.click()),
        })[action]();
    const tool =
        'text-muted-foreground inline-flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800';
    const photoButton = (
        <div className="flex flex-wrap">
            <button type="button" onClick={() => picker.current?.click()} className={tool} title={t('Photo/video')} aria-label={t('Photo/video')}>
                <ImagePlus className="size-5 text-green-600" /> <span className={open ? 'hidden sm:inline' : ''}>{t('Photo/video')}</span>
            </button>
            <button type="button" onClick={openCamera} className={tool} title={t('Camera')} aria-label={t('Camera')}>
                <Camera className="size-5 text-rose-500" /> <span className={open ? 'hidden sm:inline' : ''}>{t('Camera')}</span>
            </button>
            {!groupId && (
                <button
                    type="button"
                    onClick={() => (cameraAvailable() ? setClipping(true) : clipPicker.current?.click())}
                    className={tool}
                    title={t('Clip')}
                    aria-label={t('Clip')}
                >
                    <Film className="size-5 text-fuchsia-600" /> <span className={open ? 'hidden sm:inline' : ''}>{t('Clip')}</span>
                </button>
            )}
            <button
                type="button"
                onClick={() => (setOpen(true), setTagging(true))}
                className={tool}
                title={t('Tag friends')}
                aria-label={t('Tag friends')}
            >
                <UserPlus className="size-5 text-sky-600" />{' '}
                <span className={open ? 'hidden lg:inline' : 'hidden sm:inline'}>{t('Tag friends')}</span>
            </button>
        </div>
    );

    return (
        <form
            ref={box}
            onSubmit={submit}
            className={
                skin === 'stream'
                    ? 'bg-white px-4 py-3 dark:bg-black'
                    : skin === 'showcase'
                      ? 'bg-white px-4 py-3 sm:rounded-lg sm:border sm:border-slate-200 dark:bg-black sm:dark:border-neutral-800'
                      : skin === 'forum'
                        ? 'bg-white px-4 py-3 sm:rounded-2xl sm:border sm:border-slate-200 dark:bg-neutral-900 sm:dark:border-neutral-800'
                        : 'rounded-lg bg-white px-4 py-3 shadow-sm dark:bg-neutral-900'
            }
        >
            <input ref={picker} type="file" accept="image/*,video/*" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
            <input
                ref={nativeCamera}
                type="file"
                accept="image/*,video/*"
                capture="environment"
                className="hidden"
                onChange={(e) => addFiles(e.target.files)}
            />
            <input ref={gifPicker} type="file" accept="image/gif" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
            <input ref={clipPicker} type="file" accept="video/*" className="hidden" onChange={(e) => addClip(e.target.files?.[0])} />
            {clipping && (
                <CameraCapture
                    vertical
                    photo={false}
                    maxSeconds={CLIP_SECONDS}
                    onClose={() => setClipping(false)}
                    onVideo={addClip}
                    onPick={() => clipPicker.current?.click()}
                />
            )}
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
                    <DefaultAvatarFallback />
                </Avatar>
                {open ? (
                    // "@" names a friend, "#" makes a hashtag.
                    <TagTextarea
                        dir="auto"
                        autoFocus
                        rows={form.data.images.length ? 2 : 4}
                        wrapperClassName="flex-1"
                        className="w-full resize-none rounded-xl bg-slate-100 px-4 py-2.5 text-[0.9375rem] outline-none dark:bg-neutral-800"
                        placeholder={t("What's on your mind, :name?", { name: firstName })}
                        value={form.data.body}
                        onChange={(body) => form.setData('body', body)}
                    />
                ) : (
                    <button
                        type="button"
                        data-composer-open
                        onClick={() => setOpen(true)}
                        className="text-muted-foreground flex-1 rounded-full bg-slate-100 px-4 py-2.5 text-start text-[0.9375rem] hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                    >
                        {t("What's on your mind, :name?", { name: firstName })}
                    </button>
                )}
            </div>

            <TagFriendsPicker
                open={tagging}
                onClose={() => setTagging(false)}
                value={tagged}
                onDone={(friends) => {
                    setTagged(friends);
                    form.setData(
                        'tagged_ids',
                        friends.map((f) => f.id),
                    );
                    setTagging(false);
                }}
            />
            <FeelingPicker
                open={picking === 'feeling'}
                onClose={() => setPicking(null)}
                value={form.data.feeling}
                onPick={(feeling) => (form.setData('feeling', feeling), setPicking(null))}
            />
            <PlacePicker
                open={picking === 'place'}
                onClose={() => setPicking(null)}
                value={form.data.place}
                onPick={(place) => (form.setData('place', place), setPicking(null))}
            />
            <LifeEventPicker
                open={picking === 'life_event'}
                onClose={() => setPicking(null)}
                value={form.data.life_event}
                onPick={(event) => (form.setData('life_event', event), setPicking(null))}
            />
            {open && (
                <ExtrasChips
                    value={{ feeling: form.data.feeling, place: form.data.place, life_event: form.data.life_event }}
                    onOpen={setPicking}
                    onRemove={(key) => form.setData(key, null)}
                />
            )}
            {open && groupId && tagged.length > 0 && (
                <div className="mt-2 ps-12 text-sm">
                    <WithFriends people={tagged} linked={false} onClick={() => setTagging(true)} />
                </div>
            )}
            {open && !groupId && (
                <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 ps-12">
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
                    {tagged.length > 0 && (
                        <span className="text-sm">
                            <WithFriends people={tagged} linked={false} onClick={() => setTagging(true)} />
                        </span>
                    )}
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
                <div className={`relative mt-3 ${form.data.clip ? 'mx-auto w-fit' : ''}`}>
                    <video
                        src={videoPreview}
                        controls
                        playsInline
                        className={form.data.clip ? 'aspect-[9/16] max-h-96 rounded-lg bg-black object-cover' : 'max-h-80 w-full rounded-lg bg-black'}
                    />
                    {form.data.clip && (
                        <span className="absolute start-2 top-2 inline-flex items-center gap-1 rounded-full bg-fuchsia-600 px-2 py-0.5 text-xs font-semibold text-white">
                            <Film className="size-3.5" /> {t('Clip')}
                        </span>
                    )}
                    <button
                        type="button"
                        aria-label={t('Remove')}
                        onClick={() => form.setData((data) => ({ ...data, video: null, video_thumb: null, clip: false }))}
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
            {embed && previews.length === 0 && !videoPreview && (
                <div className="mt-3 overflow-hidden rounded-lg">
                    <ExternalEmbed embed={embed} onRemove={() => (setDismissed(typedUrl), setEmbed(null))} />
                </div>
            )}
            {form.errors.video && <p className="mt-2 text-sm text-red-600">{form.errors.video}</p>}
            {(clipError || form.errors.video_duration) && <p className="mt-2 text-sm text-red-600">{clipError || form.errors.video_duration}</p>}
            {form.errors.images && <p className="mt-2 text-sm text-red-600">{form.errors.images}</p>}
            {Object.keys(form.errors).some((k) => k.startsWith('images.')) && (
                <p className="mt-2 text-sm text-red-600">{t('One of the pictures could not be used.')}</p>
            )}

            {open ? (
                <>
                    <AddToPost
                        clip={!groupId}
                        onAction={addToPost}
                        active={{
                            photo: form.data.images.length > 0 || !!form.data.video,
                            tag: tagged.length > 0,
                            feeling: !!form.data.feeling,
                            place: !!form.data.place,
                            life_event: !!form.data.life_event,
                        }}
                    />
                    <div className="mt-3 flex items-center justify-end gap-2">
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => (form.reset(...MEDIA), setTagged([]), setClipError(''), setOpen(false), onCancel?.())}
                                className="text-muted-foreground rounded-md px-3 py-2 text-sm font-semibold whitespace-nowrap hover:bg-slate-100 dark:hover:bg-neutral-800"
                            >
                                {t('Cancel')}
                            </button>
                            <button
                                disabled={form.processing || preparing || empty}
                                className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold whitespace-nowrap text-white disabled:opacity-40"
                            >
                                {form.processing ? (form.progress ? `${form.progress.percentage ?? 0}%` : t('Publishing…')) : t('Publish')}
                            </button>
                        </div>
                    </div>
                </>
            ) : (
                <div className="mt-2 flex border-t border-slate-200 pt-2 dark:border-neutral-800">{photoButton}</div>
            )}
        </form>
    );
}
