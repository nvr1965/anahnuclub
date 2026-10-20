import { Badge, card } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Save } from 'lucide-react';
import { useState } from 'react';

interface Row {
    key: string;
    en: string;
    text: string | null;
    source: 'code' | 'auto' | 'manual' | 'missing';
}

/** The most rows drawn at once; the search narrows the rest down. */
const SHOWN = 300;

export default function LanguageTexts({
    language,
    rows,
}: {
    language: { code: string; name: string; native_name: string; rtl: boolean };
    rows: Row[];
}) {
    const t = useT();
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<'all' | 'auto' | 'manual' | 'missing'>('all');
    const [drafts, setDrafts] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});

    const q = query.trim().toLowerCase();
    const shown = rows.filter(
        (r) => (filter === 'all' || r.source === filter) && (!q || `${r.key} ${r.en} ${r.text ?? ''}`.toLowerCase().includes(q)),
    );

    const save = (key: string, text: string) =>
        router.put(
            route('admin.languages.correct', language.code),
            { key, text },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setDrafts(({ [key]: _done, ...rest }) => rest); // eslint-disable-line @typescript-eslint/no-unused-vars
                    setErrors(({ [key]: _done, ...rest }) => rest); // eslint-disable-line @typescript-eslint/no-unused-vars
                },
                onError: (e) => setErrors((all) => ({ ...all, [key]: e.text ?? Object.values(e)[0] })),
            },
        );

    const labels: Record<Row['source'], string> = {
        code: t('Built in'),
        auto: t('Machine translation'),
        manual: t('Corrected by hand'),
        missing: t('Not translated'),
    };
    const count = (source: Row['source']) => rows.filter((r) => r.source === source).length;
    const field =
        'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';

    return (
        <AdminLayout title={`${t('Interface texts')}: ${language.native_name}`}>
            <div className="space-y-4">
                <Link href={route('admin.languages.index')} className="text-muted-foreground inline-flex items-center gap-1 text-sm hover:underline">
                    <ArrowLeft className="size-4 rtl:rotate-180" /> {t('Languages')}
                </Link>
                <div className={`${card} space-y-3 p-4`}>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Correct any text: machine translation never changes what was corrected by hand. Clear the field to bring back the earlier text.',
                        )}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <input
                            className={`${field.replace('w-full', '')} min-w-0 flex-1 sm:max-w-sm`}
                            placeholder={t('Search')}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                        <select className={field.replace('w-full', '')} value={filter} onChange={(e) => setFilter(e.target.value as typeof filter)}>
                            <option value="all">
                                {t('All')} ({rows.length})
                            </option>
                            {(['auto', 'manual', 'missing'] as const).map((source) => (
                                <option key={source} value={source}>
                                    {labels[source]} ({count(source)})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className={`${card} divide-y dark:divide-neutral-800`}>
                    {shown.slice(0, SHOWN).map((row) => {
                        const draft = drafts[row.key];
                        const value = draft ?? row.text ?? '';
                        const changed = draft !== undefined && draft !== (row.text ?? '');
                        return (
                            <div key={row.key} className="grid gap-2 p-3 md:grid-cols-2">
                                <div className="min-w-0 text-sm" dir="ltr">
                                    <div className="break-words">{row.en}</div>
                                    <div className="mt-1">
                                        <Badge tone={row.source === 'manual' ? 'green' : row.source === 'missing' ? 'red' : 'gray'}>
                                            {labels[row.source]}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="flex items-start gap-2">
                                    <div className="min-w-0 flex-1">
                                        <textarea
                                            rows={Math.min(4, Math.ceil(Math.max(value.length, row.en.length) / 50) || 1)}
                                            dir={language.rtl ? 'rtl' : 'ltr'}
                                            lang={language.code}
                                            className={`${field} resize-y`}
                                            placeholder={row.en}
                                            value={value}
                                            onChange={(e) => setDrafts((all) => ({ ...all, [row.key]: e.target.value }))}
                                        />
                                        {errors[row.key] && <p className="mt-1 text-sm text-red-600">{errors[row.key]}</p>}
                                    </div>
                                    {changed && (
                                        <button
                                            type="button"
                                            title={t('Save')}
                                            aria-label={t('Save')}
                                            className="bg-brand hover:bg-brand-dark rounded-md p-2 text-white"
                                            onClick={() => save(row.key, draft)}
                                        >
                                            <Save className="size-4" />
                                        </button>
                                    )}
                                    {!changed && row.source === 'manual' && (
                                        <button
                                            type="button"
                                            title={t('Undo the correction')}
                                            aria-label={t('Undo the correction')}
                                            className="text-muted-foreground rounded-md p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                            onClick={() => save(row.key, '')}
                                        >
                                            <RotateCcw className="size-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                    {shown.length === 0 && <p className="text-muted-foreground p-4 text-sm">{t('Nothing found.')}</p>}
                    {shown.length > SHOWN && (
                        <p className="text-muted-foreground p-4 text-sm">
                            {t('Showing :shown of :total. Narrow the search to see the rest.', { shown: SHOWN, total: shown.length })}
                        </p>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
