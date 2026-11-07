import { AudienceIcon, AudiencePicker, loadFriends, useAudienceLabels } from '@/components/audience-picker';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Audience, type Author, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Lock, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Where a post is shared inside the club: one's own feed, a group, or a friend's profile. */
export type ShareTarget = 'feed' | 'group' | 'friend';

interface GroupChoice {
    id: number;
    name: string;
    avatar: string | null;
    is_private: boolean;
}

/** The member's groups, asked for once a page and kept. */
let groupsCache: Promise<GroupChoice[]> | null = null;
const loadGroups = () =>
    (groupsCache ??= fetch(route('posts.share.groups'), { headers: { Accept: 'application/json' } }).then((r) => (r.ok ? r.json() : [])));

/**
 * The "Share" window, as on Facebook: for a group or a friend the place is picked first, then the
 * member may add a few words above the post (and, outside groups, choose who sees it).
 */
export function SharePostDialog({
    postId,
    preview,
    target,
    onClose,
}: {
    postId: number;
    /** A line of the shared post, to show what is being shared. */
    preview: string;
    target: ShareTarget | null;
    onClose: () => void;
}) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const labels = useAudienceLabels();
    const [place, setPlace] = useState<{ id: number; name: string; avatar: string | null } | null>(null);
    const [options, setOptions] = useState<(Author | GroupChoice)[] | null>(null);
    const [query, setQuery] = useState('');
    const [body, setBody] = useState('');
    const [audience, setAudience] = useState<{ audience: Audience; ids: number[] }>({ audience: 'public', ids: [] });
    const [choosingAudience, setChoosingAudience] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        if (!target) return;
        setPlace(null);
        setOptions(null);
        setQuery('');
        setBody('');
        setError(false);
        setAudience({ audience: auth.user?.default_audience ?? 'public', ids: [] });
        if (target === 'group') loadGroups().then(setOptions);
        if (target === 'friend') loadFriends().then(setOptions);
        // Only when the window opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target]);

    const picking = target !== 'feed' && !place;
    const title = target === 'group' ? t('Share to a group') : target === 'friend' ? t("Share on a friend's profile") : t('Share to Feed');
    const q = query.trim().toLowerCase();
    const shown = (options ?? []).filter((o) => !q || o.name.toLowerCase().includes(q));

    const send = () => {
        if (!target) return;
        setSending(true);
        setError(false);
        router.post(
            route('posts.share', postId),
            {
                target,
                body,
                group_id: target === 'group' ? place?.id : null,
                user_id: target === 'friend' ? place?.id : null,
                ...(target === 'group' ? {} : { audience: audience.audience, audience_ids: audience.ids }),
            },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onError: () => setError(true),
                onFinish: () => setSending(false),
            },
        );
    };

    const placeholder = target === 'friend' && place ? t('Write something to :name…', { name: place.name }) : t('Say something about this…');

    return (
        <Dialog open={target !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-lg">
                <div className="flex items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                    {target !== 'feed' && place && (
                        <button
                            type="button"
                            aria-label={t('Back')}
                            className="rounded-full p-1.5 hover:bg-slate-100 dark:hover:bg-neutral-800"
                            onClick={() => setPlace(null)}
                        >
                            <ArrowLeft className="size-5 rtl:rotate-180" />
                        </button>
                    )}
                    <DialogTitle className="flex-1 text-center text-lg">{title}</DialogTitle>
                </div>
                <DialogDescription className="sr-only">{title}</DialogDescription>

                {picking ? (
                    <div className="px-4 py-3">
                        <input
                            autoFocus
                            className="focus:border-brand mb-2 w-full rounded-full border border-slate-300 bg-slate-100 px-4 py-2 text-[0.9375rem] outline-none dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder={target === 'group' ? t('Search groups') : t('Search friends')}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                        <div className="max-h-[50vh] overflow-y-auto">
                            {options === null && <p className="text-muted-foreground p-3 text-sm">{t('Loading...')}</p>}
                            {options !== null && shown.length === 0 && (
                                <p className="text-muted-foreground p-3 text-sm">
                                    {options.length
                                        ? t('Nothing found.')
                                        : target === 'group'
                                          ? t("You aren't in any groups yet.")
                                          : t('You have no friends here yet.')}
                                </p>
                            )}
                            {shown.map((option) => (
                                <button
                                    key={option.id}
                                    type="button"
                                    onClick={() => setPlace({ id: option.id, name: option.name, avatar: option.avatar ?? null })}
                                    className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start hover:bg-slate-100 dark:hover:bg-neutral-800"
                                >
                                    {target === 'group' ? (
                                        <span className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-200 dark:bg-neutral-700">
                                            {option.avatar ? (
                                                <img src={option.avatar} alt="" className="size-full object-cover" />
                                            ) : (
                                                <Users className="size-5" />
                                            )}
                                        </span>
                                    ) : (
                                        <Avatar className="size-10">
                                            <AvatarImage src={option.avatar ?? undefined} alt="" className="object-cover" />
                                            <DefaultAvatarFallback />
                                        </Avatar>
                                    )}
                                    <span className="min-w-0 flex-1 truncate font-medium">{option.name}</span>
                                    {'is_private' in option && option.is_private && <Lock className="text-muted-foreground size-4" />}
                                </button>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="space-y-3 px-4 py-3">
                        <div className="flex items-center gap-2.5">
                            <Avatar className="size-10">
                                <AvatarImage src={auth.user?.avatar ?? undefined} alt="" className="object-cover" />
                                <DefaultAvatarFallback />
                            </Avatar>
                            <div className="min-w-0 text-[0.9375rem] leading-tight">
                                <div className="truncate font-semibold">
                                    {auth.user?.name}
                                    {place && (
                                        <>
                                            {' '}
                                            <span className="text-muted-foreground inline-block rtl:-scale-x-100">▸</span> {place.name}
                                        </>
                                    )}
                                </div>
                                {target !== 'group' && (
                                    <button
                                        type="button"
                                        onClick={() => setChoosingAudience(true)}
                                        className="mt-1 inline-flex items-center gap-1 rounded-md bg-slate-200 px-2 py-0.5 text-xs font-semibold dark:bg-neutral-700"
                                    >
                                        <AudienceIcon audience={audience.audience} className="size-3" />
                                        {labels[audience.audience].title}
                                        <ChevronDown className="size-3" />
                                    </button>
                                )}
                            </div>
                        </div>
                        <textarea
                            dir="auto"
                            autoFocus
                            rows={3}
                            className="w-full resize-y rounded-xl bg-slate-100 px-4 py-2.5 text-[0.9375rem] outline-none dark:bg-neutral-800"
                            placeholder={placeholder}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                        />
                        {preview && (
                            <p className="text-muted-foreground line-clamp-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-neutral-800">
                                {preview}
                            </p>
                        )}
                        {error && <p className="text-sm text-red-600">{t('Could not share the post. Please try again.')}</p>}
                        <div className="flex justify-end">
                            <button
                                type="button"
                                disabled={sending}
                                onClick={send}
                                className="bg-brand hover:bg-brand-dark w-full rounded-md px-5 py-2 text-sm font-semibold text-white disabled:opacity-40"
                            >
                                {t('Share now')}
                            </button>
                        </div>
                    </div>
                )}
                {target !== 'group' && (
                    <AudiencePicker
                        open={choosingAudience}
                        onClose={() => setChoosingAudience(false)}
                        value={{ ...audience, makeDefault: false }}
                        showDefault={false}
                        onDone={(choice) => {
                            setAudience({ audience: choice.audience, ids: choice.ids });
                            setChoosingAudience(false);
                        }}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
