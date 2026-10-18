import { UserAvatar } from '@/components/user-avatar';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Person, type SharedData, type TranslationInfo } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Languages, MessageCircle, SendHorizontal } from 'lucide-react';
import { type FormEventHandler, type KeyboardEvent, useEffect, useRef, useState } from 'react';

interface ConversationRow {
    id: number;
    with: Person;
    last: { body: string; mine: boolean; created_at: string | null } | null;
    unread: boolean;
}

interface Message {
    id: number;
    body: string;
    mine: boolean;
    created_at: string | null;
    translation: TranslationInfo | null;
}

interface Props {
    conversations: ConversationRow[];
    conversation: { id: number; with: Person & { last_seen_at: string | null }; blocked: 'by_me' | 'by_them' | null } | null;
    messages: Message[];
}

/** How often an open chat asks for new messages (there is no live connection on shared hosting). */
const POLL_MS = 4000;

export default function Messages({ conversations, conversation, messages }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;

    useEffect(() => {
        const timer = setInterval(
            () => {
                if (document.visibilityState === 'visible') router.reload({ only: ['conversations', 'messages', 'counts'] });
            },
            conversation ? POLL_MS : POLL_MS * 3,
        );
        return () => clearInterval(timer);
    }, [conversation?.id]);

    return (
        <ClubLayout>
            <Head title={conversation ? conversation.with.name : t('Messages')} />
            <div className="mx-auto grid h-[calc(100dvh-96px)] w-full max-w-[1100px] overflow-hidden rounded-lg bg-white shadow-sm md:grid-cols-[340px_minmax(0,1fr)] dark:bg-neutral-900">
                <aside
                    className={`flex min-h-0 flex-col border-slate-200 md:border-e dark:border-neutral-800 ${conversation ? 'hidden md:flex' : 'flex'}`}
                >
                    <h1 className="px-4 pt-4 pb-2 text-2xl font-bold">{t('Messages')}</h1>
                    <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                        {conversations.length === 0 && (
                            <p className="text-muted-foreground px-2 py-6 text-center text-[15px]">
                                {t('No conversations yet. Open a member’s profile and press “Message”.')}
                            </p>
                        )}
                        {conversations.map((row) => (
                            <Link
                                key={row.id}
                                href={route('messages.show', row.id)}
                                className={`flex items-center gap-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-neutral-800 ${conversation?.id === row.id ? 'bg-brand-soft/60' : ''}`}
                            >
                                <UserAvatar person={row.with} className="size-14" />
                                <div className="min-w-0 flex-1">
                                    <div dir="auto" className={`truncate text-[15px] ${row.unread ? 'font-bold' : 'font-medium'}`}>
                                        {row.with.name}
                                    </div>
                                    {row.last && (
                                        <div
                                            className={`flex gap-1 text-[13px] ${row.unread ? 'font-semibold text-slate-900 dark:text-white' : 'text-muted-foreground'}`}
                                        >
                                            <span dir="auto" className="truncate">
                                                {row.last.mine && `${t('You')}: `}
                                                {row.last.body}
                                            </span>
                                            <span className="shrink-0">· {relativeTime(row.last.created_at, locale)}</span>
                                        </div>
                                    )}
                                </div>
                                {row.unread && <span className="bg-brand size-3 shrink-0 rounded-full" />}
                            </Link>
                        ))}
                    </div>
                </aside>

                {conversation ? (
                    <Chat key={conversation.id} conversation={conversation} messages={messages} />
                ) : (
                    <div className="text-muted-foreground hidden flex-col items-center justify-center gap-3 md:flex">
                        <MessageCircle className="size-16 opacity-30" />
                        <p className="text-[15px]">{t('Choose a conversation')}</p>
                    </div>
                )}
            </div>
        </ClubLayout>
    );
}

function Chat({ conversation, messages }: { conversation: NonNullable<Props['conversation']>; messages: Message[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const form = useForm({ body: '' });
    const scroller = useRef<HTMLDivElement>(null);
    const lastId = messages.at(-1)?.id;
    const online = conversation.with.last_seen_at && Date.now() - new Date(conversation.with.last_seen_at).getTime() < 5 * 60 * 1000;

    useEffect(() => {
        scroller.current?.scrollTo({ top: scroller.current.scrollHeight });
    }, [lastId]);

    const send: FormEventHandler = (e) => {
        e.preventDefault();
        if (!form.data.body.trim() || form.processing) return;
        form.post(route('messages.store', conversation.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) send(e);
    };

    return (
        <section className="flex min-h-0 flex-col">
            <header className="flex items-center gap-3 border-b border-slate-200 px-3 py-2 dark:border-neutral-800">
                <Link
                    href={route('messages.index')}
                    className="rounded-full p-2 hover:bg-slate-100 md:hidden dark:hover:bg-neutral-800"
                    aria-label={t('Back')}
                >
                    <ArrowLeft className="size-5 rtl:rotate-180" />
                </Link>
                <Link href={route('people.show', conversation.with.id)} className="flex min-w-0 items-center gap-3">
                    <span className="relative">
                        <UserAvatar person={conversation.with} className="size-10" />
                        {online && (
                            <span className="absolute end-0 bottom-0 size-3 rounded-full bg-green-500 ring-2 ring-white dark:ring-neutral-900" />
                        )}
                    </span>
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
                    <Bubble key={message.id} message={message} showTime={i === 0 || gap(messages[i - 1], message)} />
                ))}
            </div>

            {conversation.blocked ? (
                <div className="text-muted-foreground flex flex-wrap items-center justify-center gap-3 border-t border-slate-200 p-4 text-center text-sm dark:border-neutral-800">
                    {conversation.blocked === 'by_me' ? (
                        <>
                            {t('You blocked :name.', { name: conversation.with.name })}
                            <button
                                type="button"
                                className="text-brand font-semibold hover:underline"
                                onClick={() => router.delete(route('blocks.user.destroy', conversation.with.id), { preserveScroll: true })}
                            >
                                {t('Unblock')}
                            </button>
                        </>
                    ) : (
                        t('You can no longer reply in this chat.')
                    )}
                </div>
            ) : (
                <form onSubmit={send} className="flex items-end gap-2 border-t border-slate-200 p-3 dark:border-neutral-800">
                    <textarea
                        dir="auto"
                        rows={1}
                        autoFocus
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder={t('Aa')}
                        className="field-sizing-content max-h-40 min-h-10 flex-1 resize-none rounded-2xl bg-slate-100 px-4 py-2 text-[15px] outline-none dark:bg-neutral-800"
                    />
                    <button
                        className="text-brand rounded-full p-2 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-neutral-800"
                        disabled={form.processing || !form.data.body.trim()}
                        aria-label={t('Send')}
                    >
                        <SendHorizontal className="size-6 rtl:rotate-180" />
                    </button>
                </form>
            )}
        </section>
    );
}

/** More than 15 minutes between two messages starts a new block with its time. */
function gap(a: Message, b: Message) {
    return !a.created_at || !b.created_at || new Date(b.created_at).getTime() - new Date(a.created_at).getTime() > 15 * 60 * 1000;
}

function Bubble({ message, showTime }: { message: Message; showTime: boolean }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [original, setOriginal] = useState(false);
    const translated = message.translation?.fields.body;
    const text = translated && !original ? translated : message.body;

    return (
        <>
            {showTime && message.created_at && (
                <div className="text-muted-foreground py-2 text-center text-xs">
                    {new Date(message.created_at).toLocaleString(locale, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                </div>
            )}
            <div className={`flex ${message.mine ? 'justify-end' : 'justify-start'}`}>
                <div className="max-w-[75%]">
                    <div
                        dir="auto"
                        title={message.created_at ? new Date(message.created_at).toLocaleString(locale) : ''}
                        className={`rounded-2xl px-3 py-2 text-[15px] break-words whitespace-pre-wrap ${message.mine ? 'bg-brand text-white' : 'bg-slate-100 dark:bg-neutral-800'}`}
                    >
                        {text}
                    </div>
                    {(translated || message.translation?.pending) && (
                        <div className="text-muted-foreground inline-flex items-center gap-1 px-2 pt-0.5 text-[11px]">
                            <Languages className="size-3" />
                            {translated ? (
                                <button type="button" className="hover:underline" onClick={() => setOriginal(!original)}>
                                    {original ? t('Show translation') : t('Translated automatically · Show original')}
                                </button>
                            ) : (
                                t('Translating…')
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
