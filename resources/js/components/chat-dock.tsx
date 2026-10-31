import { Bubble, gap, type Message } from '@/components/chat-bubble';
import { UserAvatar } from '@/components/user-avatar';
import { startCall } from '@/components/video-call';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Person, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ImagePlus, Minus, Paperclip, Phone, SendHorizontal, Smile, SquarePen, ThumbsUp, Video, X } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState, useSyncExternalStore } from 'react';

interface Docked {
    id: number;
    minimized: boolean;
    with?: Person;
}

interface Chat {
    conversation: {
        id: number;
        with: Person & { last_seen_at: string | null };
        blocked: 'by_me' | 'messages_by_me' | 'by_them' | 'friends_only' | null;
        request: boolean;
        can_call: boolean;
    };
    messages: Message[];
}

/*
 * The chats open at the bottom of the page, kept outside React so they stay open
 * from page to page, and in the tab's storage so they come back after a reload.
 */
const STORE = 'docked-chats';
const listeners = new Set<() => void>();
let docked: Docked[] = (() => {
    try {
        return JSON.parse(sessionStorage.getItem(STORE) ?? '[]') as Docked[];
    } catch {
        return [];
    }
})();

function save(next: Docked[]) {
    docked = next;
    try {
        sessionStorage.setItem(STORE, JSON.stringify(next));
    } catch {
        // Without storage the chats just don't come back after a reload.
    }
    listeners.forEach((listener) => listener());
}

/** How many chats stay open side by side; the others fold into round pictures. */
const openSlots = () => (window.innerWidth >= 1280 ? 2 : 1);

/**
 * Opens a chat in a small window at the bottom of the page, as on Facebook, so the member stays where they are.
 * On a phone, or on the Messages page itself, the chat opens there instead.
 */
export function openChat(id: number, person?: Person) {
    if (window.innerWidth < 768 || window.location.pathname.startsWith('/messages')) {
        router.visit(route('messages.show', id));
        return;
    }
    const others = docked.filter((d) => d.id !== id);
    let open = 0;
    save([
        { id, minimized: false, with: person ?? docked.find((d) => d.id === id)?.with },
        ...others.map((d) => {
            if (d.minimized) return d;
            open += 1;
            return open < openSlots() ? d : { ...d, minimized: true };
        }),
    ]);
}

const update = (id: number, change: Partial<Docked>) => save(docked.map((d) => (d.id === id ? { ...d, ...change } : d)));
const closeChat = (id: number) => save(docked.filter((d) => d.id !== id));

/** The windows and the folded chats, mounted once beside the page. */
export function ChatDock() {
    const t = useT();
    const page = usePage<SharedData>();
    const chats = useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        () => docked,
        () => [],
    );

    if (!page.props.auth.user || page.url.startsWith('/messages') || chats.length === 0) return null;
    const open = chats.filter((c) => !c.minimized);
    const folded = chats.filter((c) => c.minimized);

    return (
        <div className="pointer-events-none fixed end-0 bottom-0 z-40 hidden items-end gap-3 pe-4 md:flex">
            {open
                .slice()
                .reverse()
                .map((c) => (
                    <ChatWindow key={c.id} docked={c} />
                ))}
            <div className="pointer-events-auto flex flex-col items-center gap-2 pb-4">
                {folded.map((c) => (
                    <div key={c.id} className="group relative">
                        <button type="button" onClick={() => openChat(c.id)} title={c.with?.name} className="block rounded-full shadow-lg">
                            <UserAvatar person={c.with ?? null} className="size-12" />
                        </button>
                        <button
                            type="button"
                            onClick={() => closeChat(c.id)}
                            aria-label={t('Close')}
                            className="absolute -end-1 -top-1 hidden size-5 items-center justify-center rounded-full bg-white text-slate-600 shadow group-hover:flex dark:bg-neutral-800 dark:text-neutral-200"
                        >
                            <X className="size-3" />
                        </button>
                    </div>
                ))}
                <Link
                    href={route('messages.index')}
                    aria-label={t('New message')}
                    title={t('New message')}
                    className="flex size-12 items-center justify-center rounded-full bg-white text-slate-800 shadow-lg ring-1 ring-black/5 hover:bg-slate-50 dark:bg-neutral-800 dark:text-neutral-100"
                >
                    <SquarePen className="size-5" />
                </Link>
            </div>
        </div>
    );
}

const EMOJI = [
    '😀',
    '😂',
    '😍',
    '🥰',
    '😘',
    '😉',
    '😊',
    '😎',
    '🤔',
    '😮',
    '😢',
    '😡',
    '👍',
    '👎',
    '👏',
    '🙏',
    '💪',
    '🔥',
    '❤️',
    '💔',
    '🎉',
    '🌹',
    '☀️',
    '✅',
];

/** How often an open window asks for new messages (there is no live connection on shared hosting). */
const POLL_MS = 4000;

const iconButton = 'text-brand flex size-8 items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-neutral-800 [&>svg]:size-5';

/** One docked chat: the person on top with call buttons, the messages, and Messenger's bar to write at the bottom. */
function ChatWindow({ docked }: { docked: Docked }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [chat, setChat] = useState<Chat | null>(null);
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);
    const [emoji, setEmoji] = useState(false);
    const scroller = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLTextAreaElement>(null);
    const photos = useRef<HTMLInputElement>(null);
    const attach = useRef<HTMLInputElement>(null);
    const messages = chat?.messages ?? [];
    const fetched = useAutoTranslate(messages, locale);
    const lastId = messages.at(-1)?.id;

    const load = async () => {
        try {
            const response = await fetch(route('messages.popup', docked.id), { headers: { Accept: 'application/json' } });
            if (response.status === 404) return closeChat(docked.id);
            if (!response.ok) return;
            const next = (await response.json()) as Chat;
            setChat(next);
            if (!docked.with || docked.with.avatar !== next.conversation.with.avatar) update(docked.id, { with: next.conversation.with });
        } catch {
            // Tried again on the next round.
        }
    };

    useEffect(() => {
        load();
        input.current?.focus();
        const timer = setInterval(() => document.visibilityState === 'visible' && load(), POLL_MS);
        return () => clearInterval(timer);
    }, [docked.id]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        scroller.current?.scrollTo({ top: scroller.current.scrollHeight });
    }, [lastId]);

    const send = async (text: string, attached: File[] = files) => {
        if ((!text.trim() && attached.length === 0) || sending) return;
        setSending(true);
        setError('');
        const data = new FormData();
        data.append('body', text);
        attached.forEach((file) => data.append('files[]', file));
        try {
            const response = await fetch(route('messages.store', docked.id), {
                method: 'POST',
                body: data,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            if (response.ok) {
                setBody('');
                setFiles([]);
                await load();
                router.reload({ only: ['counts'] });
            } else {
                const answer = await response.json().catch(() => ({}));
                setError((Object.values(answer.errors ?? {})[0] as string[] | undefined)?.[0] ?? answer.message ?? t('Something went wrong.'));
            }
        } catch {
            setError(t('Something went wrong.'));
        }
        setSending(false);
        input.current?.focus();
    };

    const submit = (e?: FormEvent) => {
        e?.preventDefault();
        send(body);
    };
    const addFiles = (list: FileList | null) => list?.length && setFiles([...files, ...Array.from(list)].slice(0, 10));

    const person = chat?.conversation.with ?? docked.with;
    const lastSeen = chat?.conversation.with.last_seen_at;
    const online = lastSeen && Date.now() - new Date(lastSeen).getTime() < 5 * 60 * 1000;
    const c = chat?.conversation;

    return (
        <section className="pointer-events-auto flex h-[455px] w-[328px] flex-col rounded-t-lg bg-white shadow-[0_4px_24px_rgba(0,0,0,0.18)] ring-1 ring-black/5 dark:bg-neutral-900 dark:ring-white/10">
            <header className="flex items-center gap-1 border-b border-slate-200 px-1.5 py-1.5 dark:border-neutral-800">
                <Link
                    href={person ? route('people.show', person.id) : '#'}
                    className="flex min-w-0 flex-1 items-center gap-2 rounded-md p-1 hover:bg-slate-100 dark:hover:bg-neutral-800"
                >
                    <UserAvatar person={person ? { ...person, online: !!online } : null} className="size-8" />
                    <span className="min-w-0">
                        <span dir="auto" className="block truncate text-[15px] leading-tight font-semibold">
                            {person?.name ?? '…'}
                        </span>
                        <span className="text-muted-foreground block truncate text-[12px] leading-tight">
                            {online ? t('Active now') : lastSeen ? t('Last seen :time', { time: relativeTime(lastSeen, locale) }) : ''}
                        </span>
                    </span>
                </Link>
                {c?.can_call && !c.blocked && (
                    <>
                        <button
                            type="button"
                            className={iconButton}
                            aria-label={t('Audio call')}
                            title={t('Audio call')}
                            onClick={() => startCall(c.id, false, c.with.name)}
                        >
                            <Phone className="fill-current" />
                        </button>
                        <button
                            type="button"
                            className={iconButton}
                            aria-label={t('Video call')}
                            title={t('Video call')}
                            onClick={() => startCall(c.id, true, c.with.name)}
                        >
                            <Video className="fill-current" />
                        </button>
                    </>
                )}
                <button
                    type="button"
                    className={iconButton}
                    aria-label={t('Minimize')}
                    title={t('Minimize')}
                    onClick={() => update(docked.id, { minimized: true })}
                >
                    <Minus />
                </button>
                <button type="button" className={iconButton} aria-label={t('Close')} title={t('Close')} onClick={() => closeChat(docked.id)}>
                    <X />
                </button>
            </header>

            <div ref={scroller} className="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-3">
                {chat === null && <p className="text-muted-foreground p-6 text-center text-sm">…</p>}
                {chat && messages.length === 0 && person && (
                    <div className="flex flex-col items-center gap-2 py-6 text-center">
                        <UserAvatar person={person} className="size-16" />
                        <div dir="auto" className="font-semibold">
                            {person.name}
                        </div>
                        <p className="text-muted-foreground text-[13px]">{t('Say hello! Messages are translated into your friend’s language.')}</p>
                    </div>
                )}
                {messages.map((message, i) => (
                    <Bubble
                        key={message.id}
                        message={message}
                        fetched={fetched('message', message.id)?.body}
                        showTime={i === 0 || gap(messages[i - 1], message)}
                    />
                ))}
            </div>

            {c && (c.blocked || c.request) ? (
                // Answering a request or lifting a block is done on the full page, where all the choices are.
                <div className="text-muted-foreground border-t border-slate-200 p-3 text-center text-[13px] dark:border-neutral-800">
                    {c.request && !c.blocked
                        ? t(':name is not your friend and wants to write to you. Answer to accept, or choose:', { name: c.with.name })
                        : t('You can no longer reply in this chat.')}{' '}
                    <Link href={route('messages.show', c.id)} className="text-brand font-semibold hover:underline">
                        {t('Open messages')}
                    </Link>
                </div>
            ) : (
                <form
                    onSubmit={submit}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        addFiles(e.dataTransfer.files);
                    }}
                    className="relative px-1.5 pt-1 pb-1.5"
                >
                    {files.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 px-1 pb-1.5">
                            {files.map((file, i) => (
                                <span
                                    key={i}
                                    className="inline-flex max-w-full items-center gap-1 rounded-lg bg-slate-100 py-0.5 ps-1 pe-0.5 text-[12px] dark:bg-neutral-800"
                                >
                                    {file.type.startsWith('image/') ? (
                                        <img src={URL.createObjectURL(file)} alt="" className="size-7 rounded object-cover" />
                                    ) : (
                                        <Paperclip className="text-muted-foreground size-3.5 shrink-0" />
                                    )}
                                    <span dir="auto" className="max-w-24 truncate">
                                        {file.name}
                                    </span>
                                    <button type="button" aria-label={t('Remove')} onClick={() => setFiles(files.filter((_, j) => j !== i))}>
                                        <X className="size-3.5" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}
                    {error && <p className="px-1 pb-1 text-[12px] text-red-600">{error}</p>}
                    {emoji && (
                        <div className="absolute end-2 bottom-12 z-10 grid w-[232px] grid-cols-6 gap-0.5 rounded-xl bg-white p-2 shadow-lg ring-1 ring-black/5 dark:bg-neutral-900 dark:ring-white/10">
                            {EMOJI.map((e) => (
                                <button
                                    key={e}
                                    type="button"
                                    className="rounded-md p-1 text-[22px] leading-none hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    onClick={() => {
                                        setBody(body + e);
                                        setEmoji(false);
                                        input.current?.focus();
                                    }}
                                >
                                    {e}
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="flex items-end gap-0.5">
                        <input
                            ref={photos}
                            type="file"
                            accept="image/*,video/*"
                            multiple
                            hidden
                            onChange={(e) => (addFiles(e.target.files), (e.target.value = ''))}
                        />
                        <input ref={attach} type="file" multiple hidden onChange={(e) => (addFiles(e.target.files), (e.target.value = ''))} />
                        <button
                            type="button"
                            className={iconButton}
                            aria-label={t('Add photos')}
                            title={t('Add photos')}
                            onClick={() => photos.current?.click()}
                        >
                            <ImagePlus />
                        </button>
                        <button
                            type="button"
                            className={iconButton}
                            aria-label={t('Attach a file')}
                            title={t('Attach a file')}
                            onClick={() => attach.current?.click()}
                        >
                            <Paperclip />
                        </button>
                        <div className="flex min-w-0 flex-1 items-end rounded-[18px] bg-slate-100 dark:bg-neutral-800">
                            <textarea
                                ref={input}
                                dir="auto"
                                rows={1}
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) submit(e);
                                }}
                                onPaste={(e) => e.clipboardData.files.length > 0 && addFiles(e.clipboardData.files)}
                                placeholder={t('Aa')}
                                className="field-sizing-content max-h-28 min-h-9 min-w-0 flex-1 resize-none bg-transparent py-2 ps-3 text-[15px] leading-5 outline-none"
                            />
                            <button type="button" className={iconButton} aria-label={t('Emoji')} title={t('Emoji')} onClick={() => setEmoji(!emoji)}>
                                <Smile />
                            </button>
                        </div>
                        {body.trim() || files.length ? (
                            <button className={iconButton} disabled={sending} aria-label={t('Send')} title={t('Send')}>
                                <SendHorizontal className="rtl:rotate-180" />
                            </button>
                        ) : (
                            // As in Messenger, an empty box sends a thumbs up.
                            <button
                                type="button"
                                className={iconButton}
                                disabled={sending}
                                aria-label={t('Send a like')}
                                title={t('Send a like')}
                                onClick={() => send('👍', [])}
                            >
                                <ThumbsUp className="fill-current" />
                            </button>
                        )}
                    </div>
                </form>
            )}
        </section>
    );
}
