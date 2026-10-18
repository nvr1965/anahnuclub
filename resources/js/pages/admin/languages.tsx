import { Badge, card, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';

interface Row {
    code: string;
    name: string;
    native_name: string;
    rtl: boolean;
    enabled: boolean;
}

export default function AdminLanguages({ languages }: { languages: Row[] }) {
    const t = useT();

    return (
        <AdminLayout title={t('Languages')}>
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {languages.map((language) => (
                    <label key={language.code} className="flex cursor-pointer items-center gap-3 p-3">
                        <input
                            type="checkbox"
                            className="accent-brand size-4"
                            checked={language.enabled}
                            onChange={() => send('patch', route('admin.languages.update', language.code), { enabled: !language.enabled })}
                        />
                        <div className="flex-1">
                            <span className="font-medium">{language.native_name}</span>{' '}
                            <span className="text-muted-foreground text-sm">
                                {language.name} · {language.code}
                            </span>
                        </div>
                        {language.rtl && <Badge>RTL</Badge>}
                    </label>
                ))}
            </div>
            <p className="text-muted-foreground mt-3 text-sm">{t('A new language is added on the server with: php artisan language:add')}</p>
        </AdminLayout>
    );
}
