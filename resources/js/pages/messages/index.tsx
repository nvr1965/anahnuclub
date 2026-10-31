import { Bubble, fileSize, gap, type Message } from '@/components/chat-bubble';
import { EMOJI } from '@/components/chat-dock';
import { PanelMenu, panelMenuItem } from '@/components/notification-bell';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { startCall } from '@/components/video-call';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import { useKeyboardViewport } from '@/hooks/use-keyboard-viewport';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { relativeTime, shortAgo } from '@/lib/relative-time';
import { type Person, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Check,
    Ellipsis,
    FileText,
    ImagePlus,
    MessageCircle,
    MessageCircleOff,
    MessageCircleQuestion,
    Paperclip,
    Phone,
    Search,
    SendHorizontal,
    Smile,
    SquarePen,
    ThumbsUp,
    Trash2,
    Video,
    X,
} from 'lucide-react';
import { type FormEventHandler, type KeyboardEvent, useEffect, useRef, useState } from 'react';

interface ConversationRow {
    id: number;
    with: Person;
    last: { body: string; mine: boolean; created_at: string | null } | null;
    unread: boolean;
    request: boolean;
}

interface Props {
    conversations: ConversationRow[];
    /** Chats non-friends started that wait to be accepted, as on Facebook. */
    requests: ConversationRow[];
    friends: Person[];
    /** Friends for the row over the chats, those online first. */
    contacts: Person[];
    conversation: {
        id: number;
        with: Person & { last_seen_at: string | null };
        blocked: 'by_me' | 'messages_by_me' | 'by_them' | 'friends_only' | null;
        request: boolean;
        friends: boolean;
        /** The other member let this chat in and still takes messages, so they can be called. */
        can_call: boolean;
    } | null;
    messages: Message[];
}

const barButton =
    'text-brand flex size-10 shrink-0 items-center justify-center rounded-full hover:bg-slate-100 disabled:opacity-40 dark:hover:bg-neutral-800 [&>svg]:size-6';

/** How often an open chat asks for new messages (there is no live connection on shared hosting). */
const POLL_MS = 4000;

export default function Messages({ conversations, requests, friends, contacts, conversation, messages }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [tab, setTab] = useState<'all' | 'unread' | 'requests'>(conversation?.request ? 'requests' : 'all');
    // The pen at the top: pick a friend to write to, as Messenger's "New message".
    const [composing, setComposing] = useState(false);
    const [query, setQuery] = useState('');
    const search = useRef<HTMLInputElement>(null);
    const needle = query.trim().toLocaleLowerCase();
    const matches = (person: Person) => !needle || person.name.toLocaleLowerCase().includes(needle);
    const rows = (tab === 'requests' ? requests : conversations)
        .filter((row) => tab !== 'unread' || row.unread)
        .filter((row) => matches(row.with) || (needle && row.last?.body.toLocaleLowerCase().includes(needle)));
    // Searching also finds friends there is no chat with yet.
    const people = composing
        ? [...contacts, ...friends.filter((f) => !contacts.some((c) => c.id === f.id))].filter(matches)
        : needle
          ? friends.filter(matches)
          : [];
    const startChat = (person: Person) => router.post(route('messages.start', person.id));
    const unread = conversations.filter((c) => c.unread).length;

    // A request just accepted moves to the chats, and the list follows it there.
    useEffect(() => {
        if (conversation && !conversation.request && tab === 'requests') setTab('all');
    }, [conversation?.id, conversation?.request]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        const timer = setInterval(
            () => {
                if (document.visibilityState === 'visible')
                    // The open chat too: a friendship accepted meanwhile brings up the call buttons.
                    router.reload({ only: ['conversations', 'requests', 'friends', 'contacts', 'conversation', 'messages', 'counts'] });
            },
            conversation ? POLL_MS : POLL_MS * 3,
        );
        return () => clearInterval(timer);
    }, [conversation?.id]);

    const markAllRead = async () => {
        await fetch(route('messages.read'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        router.reload({ only: ['conversations', 'counts'] });
    };

    return (
        <ClubLayout full>
            <Head title={conversation ? conversation.with.name : t('Messages')} />
            {/* Messenger on Facebook: the chats down the start side, the open chat filling the rest of the screen. */}
            <div className="grid h-full grid-cols-1 md:grid-cols-[360px_minmax(0,1fr)]">
                <aside
                    className={`min-h-0 min-w-0 flex-col border-slate-200 bg-white md:border-e dark:border-neutral-800 dark:bg-neutral-900 ${conversation ? 'hidden md:flex' : 'flex'}`}
                >
                    <div className="flex items-center justify-between px-4 pt-4 pb-2">
                        <h1 className="text-2xl font-bold">{composing ? t('New message') : t('Chats')}</h1>
                        <div className="flex gap-2">
                            {!composing && (
                                <PanelMenu>
                                    {(hide) => (
                                        <>
                                            <button
                                                type="button"
                                                className={panelMenuItem}
                                                onClick={() => {
                                                    hide();
                                                    markAllRead();
                                                }}
                                            >
                                                <Check />
                                                {t('Mark all chats as read')}
                                            </button>
                                            <button
                                                type="button"
                                                className={panelMenuItem}
                                                onClick={() => {
                                                    hide();
                                                    setTab('requests');
                                                }}
                                            >
                                                <MessageCircleQuestion />
                                                {t('Message requests')}
                                            </button>
                                        </>
                                    )}
                                </PanelMenu>
                            )}
                            <button
                                type="button"
                                onClick={() => {
                                    setComposing(!composing);
                                    setQuery('');
                                    search.current?.focus();
                                }}
                                aria-label={composing ? t('Close') : t('New message')}
                                title={composing ? t('Close') : t('New message')}
                                className="flex size-9 items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                            >
                                {composing ? <X className="size-5" /> : <SquarePen className="size-5" />}
                            </button>
                        </div>
                    </div>
                    <label className="mx-4 mb-3 flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 dark:bg-neutral-800">
                        <Search className="text-muted-foreground size-4 shrink-0" />
                        <input
                            ref={search}
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={composing ? t('Search friends') : t('Search messages')}
                            className="min-w-0 flex-1 bg-transparent text-[15px] outline-none"
                        />
                        {query && (
                            <button type="button" onClick={() => setQuery('')} aria-label={t('Clear')} className="text-muted-foreground">
                                <X className="size-4" />
                            </button>
                        )}
                    </label>

                    {!composing && (
                        <div className="flex gap-1 px-3 pb-2">
                            {(
                                [
                                    ['all', t('All'), 0],
                                    ['unread', t('Unread'), unread],
                                    ['requests', t('Requests'), requests.length],
                                ] as const
                            ).map(([key, label, count]) => (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={tab === key}
                                    onClick={() => setTab(key)}
                                    className="aria-pressed:bg-brand-soft aria-pressed:text-brand dark:aria-pressed:bg-brand/20 rounded-full px-3 py-1.5 text-[15px] font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                                >
                                    {label}
                                    {count > 0 && <span className="ms-1 text-[13px] opacity-80">{count}</span>}
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                        {!composing && (
                            <>
                                {tab === 'requests' && (
                                    <p className="text-muted-foreground px-2 pb-2 text-[13px]">
                                        {t('Messages from people who are not your friends.')}
                                    </p>
                                )}
                                {rows.length === 0 && people.length === 0 && (
                                    <p className="text-muted-foreground px-2 py-8 text-center text-[15px]">
                                        {needle
                                            ? t('Nothing found.')
                                            : tab === 'requests'
                                              ? t('No requests.')
                                              : tab === 'unread'
                                                ? t('No unread chats.')
                                                : t('No conversations yet. Open a member’s profile and press “Message”.')}
                                    </p>
                                )}
                                {rows.map((row) => (
                                    <Link
                                        key={row.id}
                                        href={route('messages.show', row.id)}
                                        preserveScroll
                                        className={`flex items-center gap-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800 ${conversation?.id === row.id ? 'bg-slate-100 dark:bg-neutral-800' : ''}`}
                                    >
                                        <UserAvatar person={row.with} className="size-14" />
                                        <span className="min-w-0 flex-1">
                                            <span dir="auto" className={`block truncate text-[15px] ${row.unread ? 'font-bold' : 'font-medium'}`}>
                                                {row.with.name}
                                            </span>
                                            <span
                                                className={`flex min-w-0 gap-1 text-[13px] ${row.unread ? 'font-semibold text-slate-900 dark:text-white' : 'text-muted-foreground'}`}
                                            >
                                                <span dir="auto" className="min-w-0 truncate">
                                                    {row.last && (
                                                        <>
                                                            {row.last.mine && `${t('You')}: `}
                                                            {row.last.body}
                                                        </>
                                                    )}
                                                </span>
                                                {row.last?.created_at && <span className="shrink-0">· {shortAgo(row.last.created_at, locale)}</span>}
                                            </span>
                                        </span>
                                        {row.unread && <span className="bg-brand size-3 shrink-0 rounded-full" />}
                                    </Link>
                                ))}
                            </>
                        )}
                        {people.length > 0 && (
                            <>
                                {!composing && <h2 className="text-muted-foreground px-2 pt-3 pb-1 text-[13px] font-semibold">{t('Friends')}</h2>}
                                {people.map((friend) => (
                                    <button
                                        key={friend.id}
                                        type="button"
                                        onClick={() => startChat(friend)}
                                        className="flex w-full items-center gap-3 rounded-lg p-2 text-start hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    >
                                        <UserAvatar person={friend} className="size-11" />
                                        <span dir="auto" className="min-w-0 flex-1 truncate text-[15px] font-medium">
                                            {friend.name}
                                        </span>
                                    </button>
                                ))}
                            </>
                        )}
                        {composing && people.length === 0 && (
                            <p className="text-muted-foreground px-2 py-8 text-center text-[15px]">
                                {needle ? t('Nothing found.') : t('No friends yet.')}
                            </p>
                        )}
                    </div>
                </aside>

                <div className={`min-h-0 min-w-0 bg-[#f0f2f5] p-0 md:p-3 dark:bg-neutral-950 ${conversation ? 'flex' : 'hidden md:flex'}`}>
                    <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-white md:rounded-lg md:shadow-sm dark:bg-neutral-900">
                        {conversation ? (
                            <Chat key={conversation.id} conversation={conversation} messages={messages} />
                        ) : (
                            <div className="flex flex-1 flex-col items-center justify-center gap-4">
                                <span className="from-brand-soft flex size-24 items-center justify-center rounded-full bg-gradient-to-br to-white shadow-inner dark:from-neutral-800 dark:to-neutral-900">
                                    <MessageCircle className="text-brand size-12" />
                                </span>
                                <p className="text-[17px] font-bold">{t('No chat selected')}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ClubLayout>
    );
}

function Chat({ conversation, messages }: { conversation: NonNullable<Props['conversation']>; messages: Message[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const form = useForm<{ body: string; files: File[] }>({ body: '', files: [] });
    const picker = useRef<HTMLInputElement>(null);
    const photos = useRef<HTMLInputElement>(null);
    const [emoji, setEmoji] = useState(false);
    // Messages not yet in the reader's language (say, after switching it) are translated on opening the chat.
    const fetched = useAutoTranslate(messages, locale);
    const scroller = useRef<HTMLDivElement>(null);
    const lastId = messages.at(-1)?.id;
    const online = conversation.with.last_seen_at && Date.now() - new Date(conversation.with.last_seen_at).getTime() < 5 * 60 * 1000;

    useEffect(() => {
        scroller.current?.scrollTo({ top: scroller.current.scrollHeight });
    }, [lastId]);

    // When the keyboard opens, the chat shrinks to the space above it: the header stays on top, the box to write right over the keys.
    const panel = useRef<HTMLElement>(null);
    useKeyboardViewport(panel);
    useEffect(() => {
        const keepBottom = () => scroller.current?.scrollTo({ top: scroller.current.scrollHeight });
        window.visualViewport?.addEventListener('resize', keepBottom);
        return () => window.visualViewport?.removeEventListener('resize', keepBottom);
    }, []);

    // The page under a full-screen chat on a phone stays still.
    useEffect(() => {
        if (!window.matchMedia('(max-width: 767px)').matches) return;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = '';
        };
    }, []);

    const send: FormEventHandler = (e) => {
        e.preventDefault();
        if ((!form.data.body.trim() && !form.data.files.length) || form.processing) return;
        form.post(route('messages.store', conversation.id), {
            preserveScroll: true,
            forceFormData: form.data.files.length > 0,
            onSuccess: () => form.reset(),
        });
    };

    const addFiles = (list: FileList | null) => {
        if (list?.length) form.setData('files', [...form.data.files, ...Array.from(list)].slice(0, 10));
    };
    const fileErrors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message);

    const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) send(e);
    };

    return (
        // On phones an open chat takes the whole screen, as in Messenger: its top stays put and the box to write sits at the bottom.
        <section
            ref={panel}
            className="fixed inset-0 z-50 flex flex-col bg-white pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] md:static md:z-auto md:min-h-0 md:flex-1 md:bg-transparent md:p-0 dark:bg-neutral-900"
        >
            <header className="flex items-center gap-3 border-b border-slate-200 px-3 py-2 dark:border-neutral-800">
                <Link
                    href={route('messages.index')}
                    className="rounded-full p-2 hover:bg-slate-100 md:hidden dark:hover:bg-neutral-800"
                    aria-label={t('Back')}
                >
                    <ArrowLeft className="size-5 rtl:rotate-180" />
                </Link>
                <Link href={route('people.show', conversation.with.id)} className="flex min-w-0 items-center gap-3">
                    <UserAvatar person={{ ...conversation.with, online: !!online }} className="size-10" />
                    <span className="min-w-0">
                        <span dir="auto" className="block truncate font-semibold">
                            {conversation.with.name}
                        </span>
                        <span className="text-muted-foreground block text-[13px]">
                            {online
                                ? t('Active now')
                                : conversation.with.last_seen_at
                                  ? t('Last seen :time', { time: relativeTime(conversation.with.last_seen_at, locale) })
                                  : ''}
                        </span>
                    </span>
                </Link>
                <div className="ms-auto flex items-center">
                    {conversation.can_call && !conversation.blocked && (
                        <>
                            <button
                                type="button"
                                className="text-brand rounded-full p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                aria-label={t('Audio call')}
                                title={t('Audio call')}
                                onClick={() => startCall(conversation.id, false, conversation.with.name)}
                            >
                                <Phone className="size-5" />
                            </button>
                            <button
                                type="button"
                                className="text-brand rounded-full p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                aria-label={t('Video call')}
                                title={t('Video call')}
                                onClick={() => startCall(conversation.id, true, conversation.with.name)}
                            >
                                <Video className="size-5" />
                            </button>
                        </>
                    )}
                    <ChatMenu conversation={conversation} />
                </div>
            </header>

            <div ref={scroller} className="min-h-0 flex-1 space-y-1 overflow-y-auto px-4 py-4">
                {messages.length === 0 && (
                    <div className="flex flex-col items-center gap-2 py-10 text-center">
                        <UserAvatar person={conversation.with} className="size-20" fallbackClassName="text-2xl" />
                        <div dir="auto" className="font-semibold">
                            {conversation.with.name}
                        </div>
                        <p className="text-muted-foreground text-[15px]">{t('Say hello! Messages are translated into your friend’s language.')}</p>
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

            {conversation.request && !conversation.blocked && <RequestBar conversation={conversation} />}

            {conversation.blocked ? (
                <div className="text-muted-foreground flex flex-wrap items-center justify-center gap-3 border-t border-slate-200 p-4 text-center text-sm dark:border-neutral-800">
                    {conversation.blocked === 'by_me' || conversation.blocked === 'messages_by_me' ? (
                        <>
                            {conversation.blocked === 'by_me'
                                ? t('You blocked :name.', { name: conversation.with.name })
                                : t('You blocked messages from :name.', { name: conversation.with.name })}
                            <button
                                type="button"
                                className="text-brand font-semibold hover:underline"
                                onClick={() =>
                                    router.delete(
                                        route(
                                            conversation.blocked === 'by_me' ? 'blocks.user.destroy' : 'blocks.messages.destroy',
                                            conversation.with.id,
                                        ),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('Unblock')}
                            </button>
                        </>
                    ) : conversation.blocked === 'friends_only' ? (
                        t(':name takes messages from friends only.', { name: conversation.with.name })
                    ) : (
                        t('You can no longer reply in this chat.')
                    )}
                </div>
            ) : (
                <form
                    onSubmit={send}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        addFiles(e.dataTransfer.files);
                    }}
                    className="relative space-y-2 border-t border-slate-200 p-3 dark:border-neutral-800"
                >
                    {form.data.files.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {form.data.files.map((file, i) => (
                                <span
                                    key={i}
                                    className="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-slate-100 py-1 ps-2 pe-1 text-sm dark:bg-neutral-800"
                                >
                                    {file.type.startsWith('image/') ? (
                                        <img src={URL.createObjectURL(file)} alt="" className="size-8 rounded object-cover" />
                                    ) : (
                                        <FileText className="text-muted-foreground size-4 shrink-0" />
                                    )}
                                    <span dir="auto" className="max-w-48 truncate">
                                        {file.name}
                                    </span>
                                    <span className="text-muted-foreground text-xs">{fileSize(file.size)}</span>
                                    <button
                                        type="button"
                                        aria-label={t('Remove')}
                                        className="rounded p-0.5 hover:bg-slate-200 dark:hover:bg-neutral-700"
                                        onClick={() =>
                                            form.setData(
                                                'files',
                                                form.data.files.filter((_, j) => j !== i),
                                            )
                                        }
                                    >
                                        <X className="size-4" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}
                    {fileErrors.length > 0 && <p className="text-sm text-red-600">{fileErrors[0]}</p>}
                    {emoji && (
                        <div className="absolute end-14 bottom-16 z-10 grid w-[232px] grid-cols-6 gap-0.5 rounded-xl bg-white p-2 shadow-lg ring-1 ring-black/5 dark:bg-neutral-900 dark:ring-white/10">
                            {EMOJI.map((e) => (
                                <button
                                    key={e}
                                    type="button"
                                    className="rounded-md p-1 text-[22px] leading-none hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    onClick={() => {
                                        form.setData('body', form.data.body + e);
                                        setEmoji(false);
                                    }}
                                >
                                    {e}
                                </button>
                            ))}
                        </div>
                    )}
                    {/* Messenger's bar: photos, a file, the "Aa" box with emoji, and a thumbs-up that turns into Send. */}
                    <div className="flex items-end gap-1">
                        <input
                            ref={picker}
                            type="file"
                            multiple
                            hidden
                            onChange={(e) => {
                                addFiles(e.target.files);
                                e.target.value = '';
                            }}
                        />
                        <input
                            ref={photos}
                            type="file"
                            accept="image/*,video/*"
                            multiple
                            hidden
                            onChange={(e) => {
                                addFiles(e.target.files);
                                e.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            className={barButton}
                            aria-label={t('Add photos')}
                            title={t('Add photos')}
                            onClick={() => photos.current?.click()}
                        >
                            <ImagePlus />
                        </button>
                        <button
                            type="button"
                            className={barButton}
                            aria-label={t('Attach a file')}
                            title={t('Attach a file')}
                            onClick={() => picker.current?.click()}
                        >
                            <Paperclip />
                        </button>
                        <div className="flex min-w-0 flex-1 items-end rounded-[20px] bg-slate-100 dark:bg-neutral-800">
                            <textarea
                                dir="auto"
                                rows={1}
                                autoFocus
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                                onKeyDown={onKeyDown}
                                onPaste={(e) => e.clipboardData.files.length > 0 && addFiles(e.clipboardData.files)}
                                placeholder={t('Aa')}
                                className="field-sizing-content max-h-40 min-h-10 min-w-0 flex-1 resize-none bg-transparent py-2 ps-4 text-[15px] leading-6 outline-none"
                            />
                            <button type="button" className={barButton} aria-label={t('Emoji')} title={t('Emoji')} onClick={() => setEmoji(!emoji)}>
                                <Smile />
                            </button>
                        </div>
                        {form.data.body.trim() || form.data.files.length ? (
                            <button className={barButton} disabled={form.processing} aria-label={t('Send')} title={t('Send')}>
                                <SendHorizontal className="rtl:rotate-180" />
                            </button>
                        ) : (
                            <button
                                type="button"
                                className={barButton}
                                disabled={form.processing}
                                aria-label={t('Send a like')}
                                title={t('Send a like')}
                                onClick={() => router.post(route('messages.store', conversation.id), { body: '👍' }, { preserveScroll: true })}
                            >
                                <ThumbsUp className="fill-current" />
                            </button>
                        )}
                    </div>
                    {form.progress && <div className="bg-brand h-0.5 rounded" style={{ width: `${form.progress.percentage ?? 0}%` }} />}
                </form>
            )}
        </section>
    );
}

/** A chat a non-friend started: let it in, turn it down, or shut the messenger to them. */
function RequestBar({ conversation }: { conversation: NonNullable<Props['conversation']> }) {
    const t = useT();
    const button = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold';

    return (
        <div className="space-y-2 border-t border-slate-200 bg-slate-50 p-3 text-center dark:border-neutral-800 dark:bg-neutral-950">
            <p className="text-sm">
                {t(':name is not your friend and wants to write to you. Answer to accept, or choose:', { name: conversation.with.name })}
            </p>
            <div className="flex flex-wrap justify-center gap-2">
                <button
                    type="button"
                    className={`${button} bg-brand hover:bg-brand-dark text-white`}
                    onClick={() => router.post(route('messages.accept', conversation.id), {}, { preserveScroll: true })}
                >
                    <Check className="size-4" /> {t('Accept')}
                </button>
                <button
                    type="button"
                    className={`${button} bg-slate-200 hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700`}
                    onClick={() => router.post(route('messages.decline', conversation.id))}
                >
                    <Trash2 className="size-4" /> {t('Delete')}
                </button>
                <button
                    type="button"
                    className={`${button} bg-slate-200 hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700`}
                    onClick={() => router.post(route('blocks.messages.store', conversation.with.id), {}, { preserveScroll: true })}
                >
                    <MessageCircleOff className="size-4" /> {t('Block messages')}
                </button>
            </div>
        </div>
    );
}

function ChatMenu({ conversation }: { conversation: NonNullable<Props['conversation']> }) {
    const t = useT();
    const name = conversation.with.name;
    const messagesBlocked = conversation.blocked === 'messages_by_me';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="rounded-full p-2 outline-none hover:bg-slate-100 dark:hover:bg-neutral-800" aria-label={t('More')}>
                <Ellipsis className="size-5" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {conversation.blocked !== 'by_me' && (
                    <DropdownMenuItem
                        onSelect={() =>
                            messagesBlocked
                                ? router.delete(route('blocks.messages.destroy', conversation.with.id), { preserveScroll: true })
                                : window.confirm(t('Block messages from :name? Everything else stays as it is.', { name })) &&
                                  router.post(route('blocks.messages.store', conversation.with.id), {}, { preserveScroll: true })
                        }
                    >
                        <MessageCircleOff className="size-4" /> {messagesBlocked ? t('Unblock messages') : t('Block messages only')}
                    </DropdownMenuItem>
                )}
                {conversation.blocked !== 'by_me' && (
                    <DropdownMenuItem
                        onSelect={() =>
                            window.confirm(t('Block :name? You will stop seeing each other in the club.', { name })) &&
                            router.post(route('blocks.user', conversation.with.id), {}, { preserveScroll: true })
                        }
                    >
                        <Ban className="size-4" /> {t('Block completely')}
                    </DropdownMenuItem>
                )}
                {conversation.blocked === 'by_me' && (
                    <DropdownMenuItem onSelect={() => router.delete(route('blocks.user.destroy', conversation.with.id), { preserveScroll: true })}>
                        <Ban className="size-4" /> {t('Unblock')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
