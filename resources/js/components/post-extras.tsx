import { type TaggedFriend, WithFriends } from '@/components/post-tags';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { type Feeling, FEELINGS, LIFE_EVENTS, type LifeEvent, mapUrl, useExtrasLabels } from '@/lib/post-extras';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ArrowLeft, Camera, Ellipsis, Film, Flag, ImagePlus, LocateFixed, MapPin, Radio, Smile, UserPlus, X } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

export interface PostExtras {
    feeling: Feeling | null;
    place: string | null;
    life_event: LifeEvent | null;
}

/** A small "GIF" sign in place of an icon: there is none for it among the others. */
const GifIcon = () => <span className="rounded bg-teal-500 px-1 py-px text-[0.625rem] leading-none font-bold text-white">GIF</span>;

type Action = 'photo' | 'tag' | 'feeling' | 'place' | 'life_event' | 'gif' | 'camera' | 'clip';

/**
 * "Add to your post", as on Facebook: a row of icons under the text, and "…" opens all of them with their
 * names. Live video is shown as coming soon: the club has calls, not broadcasts, yet.
 */
export function AddToPost({
    onAction,
    clip = true,
    active,
}: {
    onAction: (a: Action) => void;
    clip?: boolean;
    active: Partial<Record<Action, boolean>>;
}) {
    const t = useT();
    const [more, setMore] = useState(false);
    const items: { key: Action | 'live'; label: string; icon: ReactNode }[] = [
        { key: 'photo', label: t('Photo/video'), icon: <ImagePlus className="size-6 text-green-600" /> },
        { key: 'tag', label: t('Tag people'), icon: <UserPlus className="size-6 text-sky-600" /> },
        { key: 'feeling', label: t('Feeling/activity'), icon: <Smile className="size-6 text-amber-500" /> },
        { key: 'place', label: t('Check in'), icon: <MapPin className="size-6 text-red-500" /> },
        { key: 'life_event', label: t('Life event'), icon: <Flag className="size-6 text-sky-500" /> },
        { key: 'gif', label: t('GIF'), icon: <GifIcon /> },
        { key: 'camera', label: t('Camera'), icon: <Camera className="size-6 text-rose-500" /> },
        ...(clip ? [{ key: 'clip' as const, label: t('Clip'), icon: <Film className="size-6 text-fuchsia-600" /> }] : []),
        { key: 'live', label: t('Live video'), icon: <Radio className="size-6 text-red-600" /> },
    ];
    const pick = (key: Action | 'live') => {
        if (key === 'live') return;
        setMore(false);
        onAction(key);
    };

    if (more)
        return (
            <div className="mt-3 rounded-lg border border-slate-200 dark:border-neutral-700">
                <div className="flex items-center gap-2 border-b border-slate-200 px-2 py-2 dark:border-neutral-700">
                    <button
                        type="button"
                        aria-label={t('Back')}
                        onClick={() => setMore(false)}
                        className="rounded-full p-1.5 hover:bg-slate-100 dark:hover:bg-neutral-800"
                    >
                        <ArrowLeft className="size-5 rtl:rotate-180" />
                    </button>
                    <span className="flex-1 pe-9 text-center font-semibold">{t('Add to your post')}</span>
                </div>
                <div className="grid grid-cols-1 gap-0.5 p-1.5 sm:grid-cols-2">
                    {items.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            disabled={item.key === 'live'}
                            onClick={() => pick(item.key)}
                            className="flex items-center gap-3 rounded-md px-3 py-2.5 text-start text-[0.9375rem] font-medium hover:bg-slate-100 disabled:cursor-default disabled:opacity-60 disabled:hover:bg-transparent dark:hover:bg-neutral-800"
                        >
                            <span className="flex size-7 items-center justify-center">{item.icon}</span>
                            <span className="flex-1">{item.label}</span>
                            {item.key === 'live' && (
                                <span className="text-muted-foreground rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                                    {t('Soon')}
                                </span>
                            )}
                            {item.key !== 'live' && active[item.key] && <span className="bg-brand size-2 rounded-full" />}
                        </button>
                    ))}
                </div>
            </div>
        );

    return (
        <div className="mt-3 flex items-center justify-between gap-1 rounded-lg border border-slate-200 px-1.5 py-1.5 sm:ps-3 dark:border-neutral-700">
            <span className="hidden flex-1 text-[0.9375rem] font-semibold sm:block">{t('Add to your post')}</span>
            {items.slice(0, 5).map((item) => (
                <button
                    key={item.key}
                    type="button"
                    title={item.label}
                    aria-label={item.label}
                    onClick={() => pick(item.key)}
                    className={`flex size-9 items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-neutral-800 ${active[item.key as Action] ? 'bg-brand-soft' : ''}`}
                >
                    {item.icon}
                </button>
            ))}
            <button
                type="button"
                title={t('More')}
                aria-label={t('More')}
                onClick={() => setMore(true)}
                className="text-muted-foreground flex size-9 items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-neutral-800"
            >
                <Ellipsis className="size-6" />
            </button>
        </div>
    );
}

function PickerDialog({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-md">
                <div className="border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                    <DialogTitle className="text-center text-lg">{title}</DialogTitle>
                </div>
                <DialogDescription className="sr-only">{title}</DialogDescription>
                {children}
            </DialogContent>
        </Dialog>
    );
}

const searchBox =
    'focus:border-brand w-full rounded-full border border-slate-300 bg-slate-100 px-4 py-2 text-[0.9375rem] outline-none dark:border-neutral-700 dark:bg-neutral-800';

/** "How are you feeling?": a face and a word, as on Facebook. */
export function FeelingPicker({
    open,
    onClose,
    value,
    onPick,
}: {
    open: boolean;
    onClose: () => void;
    value: Feeling | null;
    onPick: (f: Feeling | null) => void;
}) {
    const t = useT();
    const { feelings } = useExtrasLabels();
    const [query, setQuery] = useState('');
    useEffect(() => void (open && setQuery('')), [open]);
    const q = query.trim().toLowerCase();
    const shown = (Object.keys(FEELINGS) as Feeling[]).filter((f) => !q || feelings[f].toLowerCase().includes(q));

    return (
        <PickerDialog open={open} onClose={onClose} title={t('How are you feeling?')}>
            <div className="px-4 pt-3">
                <input autoFocus className={searchBox} placeholder={t('Search')} value={query} onChange={(e) => setQuery(e.target.value)} />
            </div>
            <div className="grid max-h-[55vh] grid-cols-2 gap-1 overflow-y-auto p-3">
                {shown.map((f) => (
                    <button
                        key={f}
                        type="button"
                        onClick={() => onPick(f === value ? null : f)}
                        className={`flex items-center gap-2.5 rounded-lg px-2 py-2 text-start hover:bg-slate-100 dark:hover:bg-neutral-800 ${f === value ? 'bg-brand-soft' : ''}`}
                    >
                        <span className="flex size-9 items-center justify-center rounded-full bg-slate-100 text-xl dark:bg-neutral-800">
                            {FEELINGS[f]}
                        </span>
                        <span className="min-w-0 flex-1 truncate font-medium">{feelings[f]}</span>
                    </button>
                ))}
                {shown.length === 0 && <p className="text-muted-foreground col-span-2 p-3 text-sm">{t('Nothing found.')}</p>}
            </div>
        </PickerDialog>
    );
}

/** "Life event": a new job, a wedding, aliyah… shown as a card above the words. */
export function LifeEventPicker({
    open,
    onClose,
    value,
    onPick,
}: {
    open: boolean;
    onClose: () => void;
    value: LifeEvent | null;
    onPick: (e: LifeEvent | null) => void;
}) {
    const t = useT();
    const { lifeEvents } = useExtrasLabels();
    return (
        <PickerDialog open={open} onClose={onClose} title={t('Life event')}>
            <div className="max-h-[60vh] overflow-y-auto p-2">
                {(Object.keys(LIFE_EVENTS) as LifeEvent[]).map((e) => (
                    <button
                        key={e}
                        type="button"
                        onClick={() => onPick(e === value ? null : e)}
                        className={`flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start hover:bg-slate-100 dark:hover:bg-neutral-800 ${e === value ? 'bg-brand-soft' : ''}`}
                    >
                        <span className="flex size-10 items-center justify-center rounded-full bg-slate-100 text-xl dark:bg-neutral-800">
                            {LIFE_EVENTS[e]}
                        </span>
                        <span className="font-medium">{lifeEvents[e]}</span>
                    </button>
                ))}
            </div>
        </PickerDialog>
    );
}

/**
 * "Check in": where the author is, typed or found from the device's location (the place's name comes
 * from OpenStreetMap, in the reader's language).
 */
export function PlacePicker({
    open,
    onClose,
    value,
    onPick,
}: {
    open: boolean;
    onClose: () => void;
    value: string | null;
    onPick: (p: string | null) => void;
}) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [text, setText] = useState(value ?? '');
    const [locating, setLocating] = useState(false);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        if (open) {
            setText(value ?? '');
            setFailed(false);
        }
        // Only when the window opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const locate = () => {
        if (!navigator.geolocation) return setFailed(true);
        setLocating(true);
        setFailed(false);
        navigator.geolocation.getCurrentPosition(
            async ({ coords }) => {
                try {
                    const r = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=14&lat=${coords.latitude}&lon=${coords.longitude}&accept-language=${locale}`,
                    );
                    const found = await r.json();
                    const a = found.address ?? {};
                    const town = a.city ?? a.town ?? a.village ?? a.suburb ?? a.county ?? '';
                    setText([a.amenity ?? a.tourism ?? '', town, a.country ?? ''].filter(Boolean).join(', ') || found.display_name || '');
                } catch {
                    setFailed(true);
                }
                setLocating(false);
            },
            () => (setLocating(false), setFailed(true)),
            { timeout: 10000 },
        );
    };

    return (
        <PickerDialog open={open} onClose={onClose} title={t('Where are you?')}>
            <form
                className="space-y-3 p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    // The window sits inside the post's form in React's tree: its "Done" must not publish the post.
                    e.stopPropagation();
                    onPick(text.trim() || null);
                }}
            >
                <input
                    autoFocus
                    maxLength={150}
                    className={searchBox}
                    placeholder={t('City, café, park…')}
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                />
                <button
                    type="button"
                    onClick={locate}
                    disabled={locating}
                    className="text-brand inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-semibold hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-neutral-800"
                >
                    <LocateFixed className="size-4" /> {locating ? t('Loading...') : t('Use my location')}
                </button>
                {failed && <p className="text-sm text-red-600">{t('Could not find where you are. Type the place instead.')}</p>}
                <div className="flex justify-end gap-2">
                    {value && (
                        <button
                            type="button"
                            onClick={() => onPick(null)}
                            className="text-muted-foreground me-auto rounded-md px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                        >
                            {t('Remove')}
                        </button>
                    )}
                    <button className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white">{t('Done')}</button>
                </div>
            </form>
        </PickerDialog>
    );
}

/** What was added to a post being written, each with an ✕: the feeling, the place, the life event. */
export function ExtrasChips({
    value,
    onOpen,
    onRemove,
}: {
    value: PostExtras;
    onOpen: (k: keyof PostExtras) => void;
    onRemove: (k: keyof PostExtras) => void;
}) {
    const t = useT();
    const { feelings, lifeEvents } = useExtrasLabels();
    const chips: { key: keyof PostExtras; text: string }[] = [
        ...(value.feeling ? [{ key: 'feeling' as const, text: `${FEELINGS[value.feeling]} ${t('feeling')} ${feelings[value.feeling]}` }] : []),
        ...(value.place ? [{ key: 'place' as const, text: `📍 ${value.place}` }] : []),
        ...(value.life_event ? [{ key: 'life_event' as const, text: `${LIFE_EVENTS[value.life_event]} ${lifeEvents[value.life_event]}` }] : []),
    ];
    if (!chips.length) return null;
    return (
        <div className="mt-2 flex flex-wrap gap-1.5 ps-12">
            {chips.map((chip) => (
                <span key={chip.key} className="inline-flex items-center rounded-full bg-slate-100 text-sm dark:bg-neutral-800">
                    <button type="button" dir="auto" className="py-1 ps-3 pe-1 hover:underline" onClick={() => onOpen(chip.key)}>
                        {chip.text}
                    </button>
                    <button
                        type="button"
                        aria-label={t('Remove')}
                        className="rounded-full p-1 pe-2 hover:text-red-600"
                        onClick={() => onRemove(chip.key)}
                    >
                        <X className="size-3.5" />
                    </button>
                </span>
            ))}
        </div>
    );
}

/**
 * What follows the author's name, as on Facebook: " — 😊 feeling happy with Anna · is at Haifa".
 * $onClick (in the composer) opens the matching picker instead of following links.
 */
export function PostContext({
    feeling,
    people,
    place,
    linked = true,
}: {
    feeling?: string | null;
    people: TaggedFriend[];
    place?: string | null;
    linked?: boolean;
}) {
    const t = useT();
    const { feelings } = useExtrasLabels();
    const mood = feeling && feeling in FEELINGS ? (feeling as Feeling) : null;
    if (!mood && people.length === 0 && !place) return null;
    return (
        <span className="text-muted-foreground">
            {' — '}
            {mood && (
                <>
                    {FEELINGS[mood]} {t('feeling')} <span className="font-semibold text-current">{feelings[mood]}</span>
                </>
            )}
            {people.length > 0 && (
                <>
                    {mood && ' '}
                    <WithFriends people={people} linked={linked} dash={false} />
                </>
            )}
            {place && (
                <>
                    {(mood || people.length > 0) && ' · '}
                    {t('is at')}{' '}
                    {linked ? (
                        <a
                            href={mapUrl(place)}
                            target="_blank"
                            rel="noopener nofollow"
                            dir="auto"
                            className="font-semibold text-current hover:underline"
                        >
                            {place}
                        </a>
                    ) : (
                        <span dir="auto" className="font-semibold text-current">
                            {place}
                        </span>
                    )}
                </>
            )}
        </span>
    );
}

/** A life event, a card above the post's words. */
export function LifeEventCard({ event }: { event: string }) {
    const { lifeEvents } = useExtrasLabels();
    if (!(event in LIFE_EVENTS)) return null;
    const key = event as LifeEvent;
    return (
        <div className="bg-brand-soft/60 flex flex-col items-center gap-1.5 rounded-xl px-4 py-5 text-center">
            <span className="flex size-14 items-center justify-center rounded-full bg-white text-3xl shadow-sm dark:bg-neutral-800">
                {LIFE_EVENTS[key]}
            </span>
            <span className="text-lg font-bold">{lifeEvents[key]}</span>
        </div>
    );
}
