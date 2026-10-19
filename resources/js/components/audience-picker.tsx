import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type Audience, type Author } from '@/types';
import { ArrowLeft, Check, Globe, Lock, type LucideIcon, UserCheck, UserMinus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface AudienceChoice {
    audience: Audience;
    ids: number[];
    makeDefault: boolean;
}

const icons: Record<Audience, LucideIcon> = { public: Globe, friends: Users, except: UserMinus, specific: UserCheck, only_me: Lock };

export function useAudienceLabels() {
    const t = useT();
    return {
        public: { title: t('Public'), note: t('Anyone on or off the site') },
        friends: { title: t('Friends'), note: t('Your friends on the site') },
        except: { title: t('Friends except...'), note: t("Don't show to some friends") },
        specific: { title: t('Specific friends'), note: t('Only show to some friends') },
        only_me: { title: t('Only me'), note: t('Only you can see it') },
    } satisfies Record<Audience, { title: string; note: string }>;
}

export function AudienceIcon({ audience, className = 'size-3.5' }: { audience: Audience; className?: string }) {
    const Icon = icons[audience] ?? Globe;
    return <Icon className={className} aria-hidden="true" />;
}

/** The friends list, asked for once a page and kept. */
let friendsCache: Promise<Author[]> | null = null;
const loadFriends = () =>
    (friendsCache ??= fetch(route('friends.list'), { headers: { Accept: 'application/json' } }).then((r) => (r.ok ? r.json() : [])));

/** The "Who can see your post?" window, as on Facebook: five audiences, a friend list for the two that name people. */
export function AudiencePicker({
    open,
    onClose,
    value,
    onDone,
    showDefault = true,
}: {
    open: boolean;
    onClose: () => void;
    value: AudienceChoice;
    onDone: (choice: AudienceChoice) => void;
    showDefault?: boolean;
}) {
    const t = useT();
    const getInitials = useInitials();
    const labels = useAudienceLabels();
    const [choice, setChoice] = useState(value);
    const [step, setStep] = useState<'list' | 'people'>('list');
    const [friends, setFriends] = useState<Author[] | null>(null);
    const [query, setQuery] = useState('');

    useEffect(() => {
        if (open) {
            setChoice(value);
            setStep('list');
            setQuery('');
        }
        // Only when the window opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    useEffect(() => {
        if (open && step === 'people' && friends === null) loadFriends().then(setFriends);
    }, [open, step, friends]);

    const pick = (audience: Audience) => {
        setChoice((c) => ({ ...c, audience, ids: audience === c.audience ? c.ids : [] }));
        if (audience === 'except' || audience === 'specific') setStep('people');
    };
    const toggle = (id: number) => setChoice((c) => ({ ...c, ids: c.ids.includes(id) ? c.ids.filter((x) => x !== id) : [...c.ids, id] }));

    const q = query.trim().toLowerCase();
    const shown = (friends ?? []).filter((f) => !q || f.name.toLowerCase().includes(q));
    const doneDisabled = choice.audience === 'specific' && choice.ids.length === 0;

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-md">
                <div className="flex items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                    {step === 'people' && (
                        <button
                            type="button"
                            aria-label={t('Back')}
                            className="rounded-full p-1.5 hover:bg-slate-100 dark:hover:bg-neutral-800"
                            onClick={() => setStep('list')}
                        >
                            <ArrowLeft className="size-5 rtl:rotate-180" />
                        </button>
                    )}
                    <DialogTitle className="flex-1 text-center text-lg">
                        {step === 'list' ? t('Post audience') : labels[choice.audience].title}
                    </DialogTitle>
                </div>
                <DialogDescription className="sr-only">{t('Who can see your post?')}</DialogDescription>

                {step === 'list' ? (
                    <div className="max-h-[60vh] overflow-y-auto px-2 py-3">
                        <p className="px-2 pb-1 font-semibold">{t('Who can see your post?')}</p>
                        <p className="text-muted-foreground px-2 pb-3 text-sm">
                            {t('Your post will show up in Feed, on your profile and in search results.')}
                        </p>
                        {(Object.keys(labels) as Audience[]).map((audience) => {
                            const on = choice.audience === audience;
                            const named = (audience === 'except' || audience === 'specific') && on && choice.ids.length > 0;
                            return (
                                <button
                                    key={audience}
                                    type="button"
                                    onClick={() => pick(audience)}
                                    className={`flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-start hover:bg-slate-100 dark:hover:bg-neutral-800 ${on ? 'bg-brand-soft/60' : ''}`}
                                >
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-slate-200 dark:bg-neutral-700">
                                        <AudienceIcon audience={audience} className="size-6" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold">{labels[audience].title}</span>
                                        <span className="text-muted-foreground block text-sm">
                                            {named ? t(':count people', { count: choice.ids.length }) : labels[audience].note}
                                        </span>
                                    </span>
                                    <span
                                        className={`flex size-6 shrink-0 items-center justify-center rounded-full border-2 ${on ? 'border-brand bg-brand' : 'border-slate-400'}`}
                                    >
                                        {on && <span className="size-2.5 rounded-full bg-white" />}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                ) : (
                    <div className="px-4 py-3">
                        <p className="text-muted-foreground pb-2 text-sm">
                            {choice.audience === 'except'
                                ? t('Friends you pick will not see the post.')
                                : t('Only the friends you pick will see the post.')}
                        </p>
                        <input
                            autoFocus
                            className="focus:border-brand mb-2 w-full rounded-full border border-slate-300 bg-slate-100 px-4 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-800"
                            placeholder={t('Search friends')}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                        <div className="max-h-[45vh] overflow-y-auto">
                            {friends === null && <p className="text-muted-foreground p-3 text-sm">{t('Loading...')}</p>}
                            {friends !== null && shown.length === 0 && (
                                <p className="text-muted-foreground p-3 text-sm">
                                    {friends.length ? t('Nothing found.') : t('You have no friends here yet.')}
                                </p>
                            )}
                            {shown.map((friend) => {
                                const on = choice.ids.includes(friend.id);
                                return (
                                    <button
                                        key={friend.id}
                                        type="button"
                                        onClick={() => toggle(friend.id)}
                                        className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    >
                                        <Avatar className="size-9">
                                            <AvatarImage src={friend.avatar ?? undefined} alt="" className="object-cover" />
                                            <AvatarFallback className="bg-brand-soft text-brand text-xs">{getInitials(friend.name)}</AvatarFallback>
                                        </Avatar>
                                        <span className="min-w-0 flex-1 truncate font-medium">{friend.name}</span>
                                        <span
                                            className={`flex size-5 items-center justify-center rounded border-2 ${on ? (choice.audience === 'except' ? 'border-red-500 bg-red-500' : 'border-brand bg-brand') : 'border-slate-400'}`}
                                        >
                                            {on && <Check className="size-3.5 text-white" />}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 dark:border-neutral-800">
                    {showDefault ? (
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="accent-brand size-4"
                                checked={choice.makeDefault}
                                onChange={(e) => setChoice((c) => ({ ...c, makeDefault: e.target.checked }))}
                            />
                            {t('Set as default audience')}
                        </label>
                    ) : (
                        <span />
                    )}
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="text-brand rounded-md px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                        >
                            {t('Cancel')}
                        </button>
                        <button
                            type="button"
                            disabled={doneDisabled}
                            onClick={() => onDone(choice)}
                            className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        >
                            {t('Done')}
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
