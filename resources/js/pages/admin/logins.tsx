import { AdminSearch, Badge, card, type Page, Pager } from '@/components/admin-ui';
import { useLoginMethod } from '@/components/login-method';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { describeDevice } from '@/lib/user-agent';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface Row {
    id: number;
    user: { id: number; name: string; email: string } | null;
    login: string | null;
    method: string;
    success: boolean;
    ip: string | null;
    user_agent: string | null;
    created_at: string | null;
}

interface Props {
    logs: Page<Row>;
    q: string;
    filter: string | null;
    user: { id: number; name: string } | null;
    today: number;
    failed_today: number;
}

export default function AdminLogins({ logs, q, filter, user, today, failed_today }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const method = useLoginMethod();
    const params = { user: user?.id, filter: filter ?? undefined };
    const when = (log: Row) => (log.created_at ? new Date(log.created_at).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'medium' }) : '');
    const member = (log: Row) =>
        log.user ? (
            <>
                <Link href={route('admin.logins.index', { user: log.user.id })} className="font-medium break-words hover:underline">
                    {log.user.name}
                </Link>
                <div className="text-muted-foreground text-xs break-all">{log.user.email}</div>
            </>
        ) : (
            <span className="text-muted-foreground break-all">{log.login || '—'}</span>
        );
    const ip = (log: Row) =>
        log.ip ? (
            <Link href={route('admin.logins.index', { q: log.ip })} className="hover:underline">
                {log.ip}
            </Link>
        ) : (
            '—'
        );
    const how = (log: Row) => (
        <>
            <Badge>{method(log.method)}</Badge>
            {!log.success && <Badge tone="red">{t('Failed')}</Badge>}
        </>
    );
    const tab = (value: string | null, label: string) => (
        <Link
            href={route('admin.logins.index', { ...params, filter: value ?? undefined, q: q || undefined })}
            className={`rounded-full px-3 py-1.5 text-sm ${filter === value ? 'bg-brand text-white' : 'bg-white dark:bg-neutral-900'}`}
        >
            {label}
        </Link>
    );

    return (
        <AdminLayout title={t('Login journal')} actions={<AdminSearch value={q} routeName="admin.logins.index" params={params} />}>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                {tab(null, t('All'))}
                {tab('failed', t('Failed attempts'))}
                {user && (
                    <Link
                        href={route('admin.logins.index', { filter: filter ?? undefined })}
                        className="bg-brand-soft text-brand rounded-full px-3 py-1.5 text-sm"
                    >
                        {user.name} ✕
                    </Link>
                )}
                <span className="text-muted-foreground ms-auto text-sm">
                    {t('Today: :count logins, :failed failed', { count: today, failed: failed_today })}
                </span>
            </div>
            <div className={`${card} overflow-hidden`}>
                {logs.data.length === 0 ? (
                    <p className="text-muted-foreground p-6 text-center text-sm">{t('No logins yet.')}</p>
                ) : (
                    <>
                        {/* Phones: one card per login, so nothing is squeezed into narrow columns. */}
                        <ul className="divide-y sm:hidden dark:divide-neutral-800">
                            {logs.data.map((log) => (
                                <li key={log.id} className="space-y-1.5 p-3 text-sm">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0" dir="auto">
                                            {member(log)}
                                        </div>
                                        <div className="flex shrink-0 flex-wrap justify-end gap-1">{how(log)}</div>
                                    </div>
                                    <div className="text-muted-foreground flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
                                        <span>{when(log)}</span>
                                        <span className="font-mono" dir="ltr">
                                            {ip(log)}
                                        </span>
                                        <span title={log.user_agent ?? undefined}>{describeDevice(log.user_agent)}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-start text-xs">
                                    <tr className="border-b dark:border-neutral-800">
                                        <th className="px-4 py-2 text-start font-medium whitespace-nowrap">{t('Date and time')}</th>
                                        <th className="min-w-48 px-4 py-2 text-start font-medium">{t('Member')}</th>
                                        <th className="px-4 py-2 text-start font-medium whitespace-nowrap">{t('IP address')}</th>
                                        <th className="px-4 py-2 text-start font-medium whitespace-nowrap">{t('Device')}</th>
                                        <th className="px-4 py-2 text-start font-medium whitespace-nowrap">{t('How')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="border-b last:border-0 dark:border-neutral-800">
                                            <td className="px-4 py-2 whitespace-nowrap">{when(log)}</td>
                                            <td className="min-w-48 px-4 py-2" dir="auto">
                                                {member(log)}
                                            </td>
                                            <td className="px-4 py-2 font-mono text-xs whitespace-nowrap" dir="ltr">
                                                {ip(log)}
                                            </td>
                                            <td className="px-4 py-2 whitespace-nowrap" title={log.user_agent ?? undefined}>
                                                {describeDevice(log.user_agent)}
                                            </td>
                                            <td className="space-x-1 px-4 py-2 whitespace-nowrap">{how(log)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
            <Pager page={logs} />
        </AdminLayout>
    );
}
