import { useT } from '@/lib/i18n';
import { type SharedData, type TranslationInfo } from '@/types';
import { usePage } from '@inertiajs/react';
import { FileText, Languages, Phone, PhoneMissed, Video } from 'lucide-react';
import { useState } from 'react';

/** A message in a chat, on the full Messages page and in the small window docked at the bottom. */
export interface Message {
    id: number;
    body: string;
    files: { id: number; name: string; size: number; image: boolean; url: string }[];
    mine: boolean;
    created_at: string | null;
    translation: TranslationInfo | null;
    /** A finished video or audio call, shown as a line of its own. */
    call?: { video: boolean; status: 'ended' | 'missed' | 'declined'; seconds: number | null } | null;
}

export function fileSize(bytes: number) {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/** More than 15 minutes between two messages starts a new block with its time. */
export function gap(a: Message, b: Message) {
    return !a.created_at || !b.created_at || new Date(b.created_at).getTime() - new Date(a.created_at).getTime() > 15 * 60 * 1000;
}

/** One message: its files, the text in the reader's language and a way to the original. */
export function Bubble({ message, fetched, showTime }: { message: Message; fetched?: string; showTime: boolean }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [original, setOriginal] = useState(false);
    const translated = message.translation?.fields.body ?? fetched;
    const text = translated && !original ? translated : message.body;

    return (
        <>
            {showTime && message.created_at && (
                <div className="text-muted-foreground py-2 text-center text-xs">
                    {new Date(message.created_at).toLocaleString(locale, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                </div>
            )}
            {message.call ? (
                <CallLine call={message.call} mine={message.mine} />
            ) : (
                <div className={`flex ${message.mine ? 'justify-end' : 'justify-start'}`}>
                    <div className="max-w-[75%]">
                        {message.files.length > 0 && (
                            <div className={`mb-1 flex flex-wrap gap-1 ${message.mine ? 'justify-end' : ''}`}>
                                {message.files.map((file) =>
                                    file.image ? (
                                        <a key={file.id} href={file.url} target="_blank" rel="noreferrer">
                                            <img
                                                src={file.url}
                                                alt={file.name}
                                                loading="lazy"
                                                className="max-h-60 max-w-full rounded-2xl object-cover"
                                            />
                                        </a>
                                    ) : (
                                        <a
                                            key={file.id}
                                            href={file.url}
                                            className="flex max-w-full items-center gap-2 rounded-2xl bg-slate-100 px-3 py-2 hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                                        >
                                            <FileText className="text-brand size-6 shrink-0" />
                                            <span className="min-w-0">
                                                <span dir="auto" className="block truncate text-[0.9375rem] font-medium">
                                                    {file.name}
                                                </span>
                                                <span className="text-muted-foreground block text-xs">{fileSize(file.size)}</span>
                                            </span>
                                        </a>
                                    ),
                                )}
                            </div>
                        )}
                        {text && (
                            <div
                                dir="auto"
                                title={message.created_at ? new Date(message.created_at).toLocaleString(locale) : ''}
                                className={`rounded-2xl px-3 py-2 text-[0.9375rem] break-words whitespace-pre-wrap ${message.mine ? 'bg-brand text-white' : 'bg-slate-100 dark:bg-neutral-800'}`}
                            >
                                {text}
                            </div>
                        )}
                        {(translated || message.translation?.pending) && (
                            <div className="text-muted-foreground inline-flex items-center gap-1 px-2 pt-0.5 text-[0.6875rem]">
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
            )}
        </>
    );
}

/** "Video call · 5 min", "Missed call": the call on the side of whoever made it, as in Messenger. */
function CallLine({ call, mine }: { call: NonNullable<Message['call']>; mine: boolean }) {
    const t = useT();
    const missed = call.seconds === null;
    const kind = t(call.video ? 'Video call' : 'Audio call');
    const title = !missed ? kind : call.status === 'declined' || mine ? kind : t('Missed call');
    const detail = !missed
        ? call.seconds! < 60
            ? t(':n sec', { n: call.seconds! })
            : t(':n min', { n: Math.round(call.seconds! / 60) })
        : call.status === 'declined'
          ? t('Declined')
          : mine
            ? t('No answer')
            : kind;
    const Icon = missed && !mine ? PhoneMissed : call.video ? Video : Phone;

    return (
        <div className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
            <div className="flex items-center gap-3 rounded-2xl bg-slate-100 px-3 py-2 dark:bg-neutral-800">
                <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-full ${missed && !mine ? 'bg-red-500 text-white' : 'bg-slate-300 dark:bg-neutral-600'}`}
                >
                    <Icon className="size-5" />
                </span>
                <span className="min-w-0">
                    <span className="block text-[0.9375rem] font-semibold">{title}</span>
                    <span className="text-muted-foreground block text-xs">{detail}</span>
                </span>
            </div>
        </div>
    );
}
