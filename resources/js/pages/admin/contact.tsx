import { type Page, Badge, Pager, card, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Mail, MailOpen, Trash2 } from 'lucide-react';

interface Row {
    id: number;
    name: string;
    email: string;
    subject: string | null;
    body: string;
    user_id: number | null;
    locale: string | null;
    read: boolean;
    created_at: string | null;
}

export default function AdminContact({ messages }: { messages: Page<Row> }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const icon = 'text-muted-foreground rounded-md p-2 hover:bg-slate-100 dark:hover:bg-neutral-800';

    return (
        <AdminLayout title={t('Feedback')}>
            <div className="space-y-3">
                {messages.data.length === 0 && <p className={`${card} text-muted-foreground p-6 text-center text-sm`}>{t('No messages yet.')}</p>}
                {messages.data.map((m) => (
                    <article key={m.id} className={`${card} p-4 ${m.read ? 'opacity-70' : 'border-brand border-s-4'}`}>
                        <header className="flex flex-wrap items-center gap-2">
                            <div className="min-w-0 flex-1">
                                <div className="font-semibold" dir="auto">
                                    {m.user_id ? (
                                        <Link href={route('people.show', m.user_id)} className="hover:underline">
                                            {m.name}
                                        </Link>
                                    ) : (
                                        m.name
                                    )}{' '}
                                    <a
                                        href={`mailto:${m.email}${m.subject ? `?subject=${encodeURIComponent('Re: ' + m.subject)}` : ''}`}
                                        className="text-brand text-sm font-normal hover:underline"
                                    >
                                        {m.email}
                                    </a>
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    {m.created_at && relativeTime(m.created_at, locale)} {m.locale && <Badge>{m.locale}</Badge>}
                                </div>
                            </div>
                            <button
                                type="button"
                                className={icon}
                                title={m.read ? t('Mark as unread') : t('Mark as read')}
                                onClick={() => send('patch', route('admin.contact.update', m.id))}
                            >
                                {m.read ? <Mail className="size-4" /> : <MailOpen className="size-4" />}
                            </button>
                            <button
                                type="button"
                                className={icon}
                                aria-label={t('Delete')}
                                onClick={() => window.confirm(t('Delete this message?')) && send('delete', route('admin.contact.destroy', m.id))}
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </header>
                        {m.subject && (
                            <h2 className="mt-2 font-semibold" dir="auto">
                                {m.subject}
                            </h2>
                        )}
                        <p className="mt-1 text-[15px] whitespace-pre-wrap" dir="auto">
                            {m.body}
                        </p>
                    </article>
                ))}
                <Pager page={messages} />
            </div>
        </AdminLayout>
    );
}
