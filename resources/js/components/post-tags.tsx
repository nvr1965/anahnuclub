import { loadFriends } from '@/components/audience-picker';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Author } from '@/types';
import { Link } from '@inertiajs/react';
import { Check, Hash, X } from 'lucide-react';
import { type TextareaHTMLAttributes, useEffect, useLayoutEffect, useRef, useState } from 'react';

/** A friend tagged in a post: who they are is all the composer needs. */
export type TaggedFriend = Pick<Author, 'id' | 'name'> & { avatar?: string | null };

/** Letters, marks, digits and _ of any alphabet: what a hashtag is made of. */
const WORD = /^[\p{L}\p{M}\p{N}_]*$/u;
/** One or two words after @: friends are named in full, "@Anna Bell". */
const NAME = /^[\p{L}\p{M}\p{N}_'-]*( [\p{L}\p{M}\p{N}_'-]*)?$/u;

/** The "@na" or "#ta" being typed right before the caret, if any. */
function tokenAt(text: string, caret: number): { sign: '@' | '#'; start: number; query: string } | null {
    const before = text.slice(0, caret);
    const start = Math.max(before.lastIndexOf('@'), before.lastIndexOf('#'));
    if (start < 0 || caret - start > 41) return null;
    // Not in the middle of a word or an address: "mail@site", "page#part".
    if (start > 0 && /[\p{L}\p{N}_/&]/u.test(before[start - 1])) return null;
    const sign = before[start] as '@' | '#';
    const query = before.slice(start + 1);
    return (sign === '#' ? WORD : NAME).test(query) ? { sign, start, query } : null;
}

type Hint = { kind: 'friend'; friend: Author } | { kind: 'tag'; tag: string };

/**
 * The post's text box. "@" brings up the author's friends to name, "#" the hashtags used in the club,
 * as on Facebook; a name picked goes in as "@Full Name", which the server makes a link to the friend.
 */
export function TagTextarea({
    value,
    onChange,
    wrapperClassName = '',
    ...props
}: Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'value' | 'onChange'> & {
    value: string;
    onChange: (value: string) => void;
    wrapperClassName?: string;
}) {
    const box = useRef<HTMLTextAreaElement>(null);
    const [token, setToken] = useState<ReturnType<typeof tokenAt>>(null);
    const [friends, setFriends] = useState<Author[] | null>(null);
    const [tags, setTags] = useState<string[]>([]);
    const [active, setActive] = useState(0);
    // Where the caret goes once a picked name or hashtag is in the text: right after it, before anything else is typed.
    const caretAfter = useRef<number | null>(null);
    useLayoutEffect(() => {
        if (caretAfter.current === null || !box.current) return;
        box.current.focus();
        box.current.setSelectionRange(caretAfter.current, caretAfter.current);
        caretAfter.current = null;
    }, [value]);

    const look = (text: string, caret: number | null) => setToken(caret === null ? null : tokenAt(text, caret));

    useEffect(() => {
        if (token?.sign === '@' && friends === null) loadFriends().then(setFriends);
    }, [token, friends]);

    // Hashtags already used, asked for a moment after typing stops.
    useEffect(() => {
        if (token?.sign !== '#' || token.query === '') return setTags([]);
        const ask = new AbortController();
        const wait = setTimeout(() => {
            fetch(route('hashtags.suggest', { q: token.query }), { headers: { Accept: 'application/json' }, signal: ask.signal })
                .then((r) => (r.ok ? r.json() : []))
                .then(setTags)
                .catch(() => {});
        }, 200);
        return () => (clearTimeout(wait), ask.abort());
    }, [token?.sign, token?.query]);

    const q = token?.query.toLowerCase() ?? '';
    const hints: Hint[] =
        token?.sign === '@'
            ? (friends ?? [])
                  // Matched by the start of any of their names; a name typed out in full is done.
                  .filter((f) => {
                      const name = f.name.toLowerCase();
                      return name !== q && (!q || name.startsWith(q) || name.split(/\s+/).some((w) => w.startsWith(q)));
                  })
                  .slice(0, 8)
                  .map((friend) => ({ kind: 'friend', friend }))
            : token?.sign === '#'
              ? tags.filter((tag) => tag !== q).map((tag) => ({ kind: 'tag', tag }))
              : [];
    const shown = token !== null && hints.length > 0;

    useEffect(() => setActive(0), [token?.sign, token?.query]);

    const pick = (hint: Hint) => {
        if (!token || !box.current) return;
        const caret = box.current.selectionStart ?? value.length;
        const word = hint.kind === 'friend' ? `@${hint.friend.name} ` : `#${hint.tag} `;
        const next = value.slice(0, token.start) + word + value.slice(caret).replace(/^ /, '');
        caretAfter.current = token.start + word.length;
        onChange(next);
        setToken(null);
    };

    return (
        <div className={`relative ${wrapperClassName}`}>
            <textarea
                {...props}
                ref={box}
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    look(e.target.value, e.target.selectionStart);
                }}
                onClick={(e) => look(value, e.currentTarget.selectionStart)}
                onBlur={(e) => {
                    setToken(null);
                    props.onBlur?.(e);
                }}
                onKeyDown={(e) => {
                    if (shown) {
                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            e.preventDefault();
                            setActive((i) => (i + (e.key === 'ArrowDown' ? 1 : hints.length - 1)) % hints.length);
                            return;
                        }
                        if (e.key === 'Enter' || e.key === 'Tab') {
                            e.preventDefault();
                            pick(hints[active] ?? hints[0]);
                            return;
                        }
                        if (e.key === 'Escape') {
                            e.preventDefault();
                            setToken(null);
                            return;
                        }
                    }
                    props.onKeyDown?.(e);
                }}
            />
            {shown && (
                <ul
                    role="listbox"
                    className="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900"
                >
                    {hints.map((hint, i) => (
                        <li key={hint.kind === 'friend' ? `f${hint.friend.id}` : `t${hint.tag}`} role="option" aria-selected={i === active}>
                            <button
                                type="button"
                                // Before the text box loses focus, which would close the list.
                                onMouseDown={(e) => (e.preventDefault(), pick(hint))}
                                onMouseEnter={() => setActive(i)}
                                className={`flex w-full items-center gap-2.5 px-3 py-1.5 text-start text-[0.9375rem] ${i === active ? 'bg-slate-100 dark:bg-neutral-800' : ''}`}
                            >
                                {hint.kind === 'friend' ? (
                                    <>
                                        <Avatar className="size-8">
                                            <AvatarImage src={hint.friend.avatar ?? undefined} alt="" className="object-cover" />
                                            <DefaultAvatarFallback />
                                        </Avatar>
                                        <span dir="auto" className="min-w-0 flex-1 truncate font-medium">
                                            {hint.friend.name}
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <span className="flex size-8 items-center justify-center rounded-full bg-slate-100 dark:bg-neutral-800">
                                            <Hash className="size-4" />
                                        </span>
                                        <span dir="auto" className="min-w-0 flex-1 truncate font-medium">
                                            #{hint.tag}
                                        </span>
                                    </>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** "Tag friends": who the author is with, picked from their friends, as on Facebook. */
export function TagFriendsPicker({
    open,
    onClose,
    value,
    onDone,
}: {
    open: boolean;
    onClose: () => void;
    value: TaggedFriend[];
    onDone: (friends: TaggedFriend[]) => void;
}) {
    const t = useT();
    const [chosen, setChosen] = useState(value);
    const [friends, setFriends] = useState<Author[] | null>(null);
    const [query, setQuery] = useState('');

    useEffect(() => {
        if (!open) return;
        setChosen(value);
        setQuery('');
        if (friends === null) loadFriends().then(setFriends);
        // Only when the window opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const toggle = (friend: TaggedFriend) =>
        setChosen((all) => (all.some((f) => f.id === friend.id) ? all.filter((f) => f.id !== friend.id) : [...all, friend]));
    const q = query.trim().toLowerCase();
    const shown = (friends ?? []).filter((f) => !q || f.name.toLowerCase().includes(q));

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-md">
                <div className="border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                    <DialogTitle className="text-center text-lg">{t('Tag friends')}</DialogTitle>
                </div>
                <DialogDescription className="sr-only">{t('Who are you with?')}</DialogDescription>
                <div className="px-4 py-3">
                    <input
                        autoFocus
                        className="focus:border-brand mb-2 w-full rounded-full border border-slate-300 bg-slate-100 px-4 py-2 text-[0.9375rem] outline-none dark:border-neutral-700 dark:bg-neutral-800"
                        placeholder={t('Search friends')}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    {chosen.length > 0 && (
                        <div className="mb-2 flex flex-wrap gap-1.5">
                            {chosen.map((friend) => (
                                <button
                                    key={friend.id}
                                    type="button"
                                    onClick={() => toggle(friend)}
                                    className="bg-brand-soft text-brand inline-flex items-center gap-1 rounded-full py-1 ps-3 pe-2 text-sm font-semibold"
                                >
                                    <span dir="auto">{friend.name}</span>
                                    <X className="size-3.5" aria-label={t('Remove')} />
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="max-h-[45vh] overflow-y-auto">
                        {friends === null && <p className="text-muted-foreground p-3 text-sm">{t('Loading...')}</p>}
                        {friends !== null && shown.length === 0 && (
                            <p className="text-muted-foreground p-3 text-sm">
                                {friends.length ? t('Nothing found.') : t('You have no friends here yet.')}
                            </p>
                        )}
                        {shown.map((friend) => {
                            const on = chosen.some((f) => f.id === friend.id);
                            return (
                                <button
                                    key={friend.id}
                                    type="button"
                                    onClick={() => toggle(friend)}
                                    className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start hover:bg-slate-100 dark:hover:bg-neutral-800"
                                >
                                    <Avatar className="size-9">
                                        <AvatarImage src={friend.avatar ?? undefined} alt="" className="object-cover" />
                                        <DefaultAvatarFallback />
                                    </Avatar>
                                    <span dir="auto" className="min-w-0 flex-1 truncate font-medium">
                                        {friend.name}
                                    </span>
                                    <span
                                        className={`flex size-5 items-center justify-center rounded border-2 ${on ? 'border-brand bg-brand' : 'border-slate-400'}`}
                                    >
                                        {on && <Check className="size-3.5 text-white" />}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-slate-200 px-4 py-3 dark:border-neutral-800">
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-brand rounded-md px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                    >
                        {t('Cancel')}
                    </button>
                    <button
                        type="button"
                        onClick={() => onDone(chosen)}
                        className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white"
                    >
                        {t('Done')}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/**
 * " — with Anna", " — with Anna and Boris", " — with Anna and 3 others": the friends tagged, beside the
 * author's name. With $linked the names open the profiles; in the composer they are plain.
 */
export function WithFriends({ people, linked = true, onClick }: { people: TaggedFriend[]; linked?: boolean; onClick?: () => void }) {
    const t = useT();
    if (people.length === 0) return null;
    const name = (friend: TaggedFriend) =>
        linked ? (
            <Link key={friend.id} href={route('people.show', friend.id)} className="font-semibold text-current hover:underline">
                {friend.name}
            </Link>
        ) : (
            <span key={friend.id} className="font-semibold text-current">
                {friend.name}
            </span>
        );
    const rest = people.slice(1);
    const text = (
        <>
            {' — '}
            {t('with')} {name(people[0])}
            {rest.length === 1 && (
                <>
                    {' '}
                    {t('and')} {name(rest[0])}
                </>
            )}
            {rest.length > 1 && (
                <>
                    {' '}
                    {t('and')}{' '}
                    <span className="font-semibold text-current" title={rest.map((f) => f.name).join(', ')}>
                        {t(':count others', { count: rest.length })}
                    </span>
                </>
            )}
        </>
    );

    return onClick ? (
        <button type="button" onClick={onClick} className="text-muted-foreground text-start hover:underline">
            {text}
        </button>
    ) : (
        <span className="text-muted-foreground">{text}</span>
    );
}
