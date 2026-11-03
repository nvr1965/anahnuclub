import { useT } from '@/lib/i18n';
import { type SharedData, type TranslationInfo } from '@/types';
import { usePage } from '@inertiajs/react';
import { FileText, Languages } from 'lucide-react';
import { useState } from 'react';

/** A message in a chat, on the full Messages page and in the small window docked at the bottom. */
export interface Message {
    id: number;
    body: string;
    files: { id: number; name: string; size: number; image: boolean; url: string }[];
    mine: boolean;
    created_at: string | null;
    translation: TranslationInfo | null;
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
            <div className={`flex ${message.mine ? 'justify-end' : 'justify-start'}`}>
                <div className="max-w-[75%]">
                    {message.files.length > 0 && (
                        <div className={`mb-1 flex flex-wrap gap-1 ${message.mine ? 'justify-end' : ''}`}>
                            {message.files.map((file) =>
                                file.image ? (
                                    <a key={file.id} href={file.url} target="_blank" rel="noreferrer">
                                        <img src={file.url} alt={file.name} loading="lazy" className="max-h-60 max-w-full rounded-2xl object-cover" />
                                    </a>
                                ) : (
                                    <a
                                        key={file.id}
                                        href={file.url}
                                        className="flex max-w-full items-center gap-2 rounded-2xl bg-slate-100 px-3 py-2 hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                                    >
                                        <FileText className="text-brand size-6 shrink-0" />
                                        <span className="min-w-0">
                                            <span dir="auto" className="block truncate text-[15px] font-medium">
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
                            className={`rounded-2xl px-3 py-2 text-[15px] break-words whitespace-pre-wrap ${message.mine ? 'bg-brand text-white' : 'bg-slate-100 dark:bg-neutral-800'}`}
                        >
                            {text}
                        </div>
                    )}
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
