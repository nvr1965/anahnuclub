import { card } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

interface Stats {
    users: number;
    blocked: number;
    admins: number;
    groups: number;
    posts: number;
    posts_week: number;
    comments: number;
    translations: number;
}

export default function AdminIndex({
    stats,
    mail,
    translation_driver,
}: {
    stats: Stats;
    mail: { mailer: string; problems: string[] };
    translation_driver: string;
}) {
    const t = useT();
    const [test, setTest] = useState<'busy' | { ok: boolean; text: string } | null>(null);
    const sendTest = async () => {
        setTest('busy');
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        try {
            const response = await fetch(route('admin.mail.test'), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
            });
            setTest(response.ok ? await response.json() : { ok: false, text: `HTTP ${response.status}` });
        } catch {
            setTest({ ok: false, text: t('No connection.') });
        }
    };
    const tiles = [
        { label: t('Users'), value: stats.users, note: t(':count blocked', { count: stats.blocked }), href: route('admin.users.index') },
        { label: t('Administrators'), value: stats.admins, href: route('admin.users.index', { filter: 'admins' }) },
        { label: t('Posts'), value: stats.posts, note: t(':count this week', { count: stats.posts_week }), href: route('admin.posts.index') },
        { label: t('Comments'), value: stats.comments },
        { label: t('Groups'), value: stats.groups, href: route('admin.groups.index') },
        {
            label: t('Stored translations'),
            value: stats.translations,
            note: translation_driver === 'null' ? t('Translation is off') : translation_driver,
        },
    ];

    return (
        <AdminLayout title={t('Overview')}>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {tiles.map((tile) => {
                    const body = (
                        <>
                            <div className="text-muted-foreground text-sm">{tile.label}</div>
                            <div className="mt-1 text-3xl font-semibold tabular-nums">{tile.value.toLocaleString()}</div>
                            {tile.note && <div className="text-muted-foreground mt-1 text-xs">{tile.note}</div>}
                        </>
                    );
                    return tile.href ? (
                        <Link key={tile.label} href={tile.href} className={`${card} p-4 transition hover:shadow-md`}>
                            {body}
                        </Link>
                    ) : (
                        <div key={tile.label} className={`${card} p-4`}>
                            {body}
                        </div>
                    );
                })}
            </div>

            <div className={`${card} mt-4 space-y-2 p-4 text-sm`}>
                <div className="font-semibold">{t('Letters')}</div>
                {mail.problems.length ? (
                    mail.problems.map((problem) => (
                        <p key={problem} className="text-red-600 dark:text-red-400">
                            {problem}
                        </p>
                    ))
                ) : (
                    <p className="text-muted-foreground">{t('Letters are sent with :mailer and no problems were found.', { mailer: mail.mailer })}</p>
                )}
                <button
                    type="button"
                    onClick={sendTest}
                    disabled={test === 'busy'}
                    className="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:opacity-60 dark:border-neutral-700 dark:hover:bg-neutral-800"
                >
                    {test === 'busy' ? t('Sending…') : t('Send me a test letter')}
                </button>
                {test && test !== 'busy' && (
                    <p className={test.ok ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'}>{test.text}</p>
                )}
            </div>
        </AdminLayout>
    );
}
