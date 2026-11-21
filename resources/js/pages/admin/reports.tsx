import { type Page, ActionButton, Badge, Pager, card, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Person {
    id: number;
    name: string;
}

interface Row {
    id: number;
    type: 'post' | 'comment';
    target_id: number;
    url: string | null;
    exists: boolean | null;
    snapshot: string | null;
    reason: string;
    details: string | null;
    reporter: Person | null;
    email: string | null;
    author: (Person & { blocked: boolean }) | null;
    same: number;
    handler: string | null;
    handled_at: string | null;
    note: string | null;
    created_at: string | null;
}

/** Complaints about posts and comments: remove or keep each, with a note; what was decided stays on record. */
export default function AdminReports({ reports, status, open }: { reports: Page<Row>; status: 'open' | 'removed' | 'kept'; open: number }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [notes, setNotes] = useState<Record<number, string>>({});
    const reasons: Record<string, string> = {
        spam: t('Spam or advertising'),
        abuse: t('Insults, harassment or threats'),
        hate: t('Hatred against a group of people'),
        violence: t('Violence or terrorism'),
        illegal: t('Something illegal'),
        copyright: t('It infringes my copyright'),
        privacy: t('My personal data or photo without consent'),
        other: t('Something else'),
    };
    const tabs = [
        ['open', `${t('Open')} (${open})`],
        ['removed', t('Removed')],
        ['kept', t('Kept')],
    ] as const;
    const decide = (r: Row, action: 'remove' | 'keep') => {
        if (action === 'remove' && !window.confirm(r.type === 'post' ? t('Delete this post?') : t('Delete this comment?'))) return;
        send('patch', route('admin.reports.update', r.id), { action, note: notes[r.id] ?? '' });
    };

    return (
        <AdminLayout title={t('Complaints')}>
            <div className="space-y-3">
                <div className="flex flex-wrap gap-2">
                    {tabs.map(([key, label]) => (
                        <Link
                            key={key}
                            href={route('admin.reports.index', { status: key })}
                            aria-current={status === key ? 'page' : undefined}
                            className={`rounded-full px-3 py-1.5 text-sm ${status === key ? 'bg-brand font-semibold text-white' : 'bg-white shadow-sm dark:bg-neutral-900'}`}
                        >
                            {label}
                        </Link>
                    ))}
                </div>
                {reports.data.length === 0 && <p className={`${card} text-muted-foreground p-6 text-center text-sm`}>{t('No complaints.')}</p>}
                {reports.data.map((r) => (
                    <article key={r.id} className={`${card} p-4 ${status === 'open' ? 'border-s-4 border-red-500' : 'opacity-90'}`}>
                        <header className="flex flex-wrap items-center gap-2 text-sm">
                            <Badge tone="red">{reasons[r.reason] ?? r.reason}</Badge>
                            <Badge>{r.type === 'post' ? t('A post') : t('A comment')}</Badge>
                            {r.same > 1 && <Badge tone="brand">{t(':count complaints', { count: r.same })}</Badge>}
                            {r.exists === false && <Badge>{t('Already deleted')}</Badge>}
                            <span className="text-muted-foreground">
                                {r.created_at && relativeTime(r.created_at, locale)} ·{' '}
                                {r.reporter ? (
                                    <Link href={route('people.show', r.reporter.id)} className="hover:underline">
                                        {r.reporter.name}
                                    </Link>
                                ) : (
                                    <a href={`mailto:${r.email}`} className="hover:underline">
                                        {t('Guest')} {r.email}
                                    </a>
                                )}
                            </span>
                        </header>
                        <div className="mt-2 text-sm">
                            {t('Author')}:{' '}
                            {r.author ? (
                                <Link href={route('admin.users.index', { q: r.author.name })} className="font-semibold hover:underline">
                                    {r.author.name}
                                </Link>
                            ) : (
                                '—'
                            )}
                            {r.author?.blocked && (
                                <>
                                    {' '}
                                    <Badge tone="red">{t('Blocked')}</Badge>
                                </>
                            )}
                            {r.url && r.exists !== false && (
                                <>
                                    {' · '}
                                    <a href={r.url} target="_blank" rel="noreferrer" className="text-brand hover:underline">
                                        {t('Open')}
                                    </a>
                                </>
                            )}
                        </div>
                        {r.snapshot && (
                            <blockquote
                                className="mt-2 max-h-48 overflow-y-auto rounded-md bg-slate-100 p-3 text-[0.9375rem] whitespace-pre-wrap dark:bg-neutral-800"
                                dir="auto"
                            >
                                {r.snapshot}
                            </blockquote>
                        )}
                        {r.details && (
                            <p className="mt-2 text-[0.9375rem] whitespace-pre-wrap" dir="auto">
                                {r.details}
                            </p>
                        )}
                        {status === 'open' ? (
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <input
                                    dir="auto"
                                    aria-label={t('Note for the record')}
                                    placeholder={t('Note for the record')}
                                    value={notes[r.id] ?? ''}
                                    onChange={(e) => setNotes({ ...notes, [r.id]: e.target.value })}
                                    className="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                />
                                <ActionButton danger onClick={() => decide(r, 'remove')}>
                                    {t('Remove')}
                                </ActionButton>
                                <ActionButton onClick={() => decide(r, 'keep')}>{t('Keep')}</ActionButton>
                            </div>
                        ) : (
                            <p className="text-muted-foreground mt-2 text-sm">
                                {status === 'removed' ? t('Removed') : t('Kept')}
                                {r.handler && ` · ${r.handler}`}
                                {r.handled_at && ` · ${relativeTime(r.handled_at, locale)}`}
                                {r.note && ` · ${r.note}`}
                            </p>
                        )}
                    </article>
                ))}
                <Pager page={reports} />
            </div>
        </AdminLayout>
    );
}
