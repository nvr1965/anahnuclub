import HeadingSmall from '@/components/heading-small';
import { useLoginMethod } from '@/components/login-method';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useT } from '@/lib/i18n';
import { describeDevice } from '@/lib/user-agent';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface Row {
    id: number;
    method: string;
    success: boolean;
    ip: string | null;
    user_agent: string | null;
    created_at: string | null;
}

export default function LoginHistory({ logs }: { logs: Row[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const method = useLoginMethod();

    return (
        <AppLayout breadcrumbs={[{ title: t('Login history'), href: '/settings/logins' }]}>
            <Head title={t('Login history')} />
            <SettingsLayout>
                <div className="space-y-4">
                    <HeadingSmall
                        title={t('Login history')}
                        description={t('Your recent sign-ins. If you see one that was not you, change your password.')}
                    />
                    {logs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No logins yet.')}</p>
                    ) : (
                        <ul className="divide-y text-sm dark:divide-neutral-800">
                            {logs.map((log) => (
                                <li key={log.id} className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                                    <div>
                                        <div className="font-medium">
                                            {log.created_at &&
                                                new Date(log.created_at).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' })}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {[describeDevice(log.user_agent), method(log.method)].filter(Boolean).join(' · ')}
                                        </div>
                                    </div>
                                    <div className="text-end">
                                        <div className="font-mono text-xs" dir="ltr">
                                            {log.ip}
                                        </div>
                                        {!log.success && <div className="text-xs font-medium text-red-600">{t('Wrong password')}</div>}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
