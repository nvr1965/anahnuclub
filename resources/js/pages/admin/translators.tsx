import { Badge, card } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FlaskConical } from 'lucide-react';
import { useState } from 'react';

interface Translator {
    id: string;
    label: string;
    price: 'free' | 'free_tier' | 'paid';
    note: string;
    ready: boolean;
    needs: string[];
    on: boolean;
    daily_chars: number;
    used_today: number;
}

export default function Translators({ translators }: { translators: Translator[] }) {
    const t = useT();
    const { locale, languages } = usePage<SharedData>().props;
    const [rows, setRows] = useState(translators);
    const [saving, setSaving] = useState(false);
    const [checkLocale, setCheckLocale] = useState(locale === 'en' ? 'ru' : locale);
    const [checks, setChecks] = useState<Record<string, { ok: boolean; text: string } | 'busy'>>({});
    const changed = JSON.stringify(rows) !== JSON.stringify(translators);

    const set = (index: number, patch: Partial<Translator>) => setRows((all) => all.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    const move = (index: number, by: number) =>
        setRows((all) => {
            const next = [...all];
            [next[index], next[index + by]] = [next[index + by], next[index]];
            return next;
        });

    const save = () =>
        router.put(
            route('admin.translators.update'),
            { translators: rows.map(({ id, on, daily_chars }) => ({ id, on, daily_chars })) },
            { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) },
        );

    const check = async (id: string) => {
        setChecks((all) => ({ ...all, [id]: 'busy' }));
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        try {
            const response = await fetch(route('admin.translators.check', id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ locale: checkLocale }),
            });
            const data = response.ok ? await response.json() : { ok: false, text: `HTTP ${response.status}` };
            setChecks((all) => ({ ...all, [id]: data }));
        } catch {
            setChecks((all) => ({ ...all, [id]: { ok: false, text: t('No connection.') } }));
        }
    };

    const price: Record<Translator['price'], { text: string; tone: 'green' | 'brand' | 'gray' }> = {
        free: { text: t('Free'), tone: 'green' },
        free_tier: { text: t('Free up to a limit'), tone: 'brand' },
        paid: { text: t('Paid'), tone: 'gray' },
    };
    const field =
        'focus:border-brand rounded-md border border-slate-300 bg-white px-2 py-1.5 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';

    return (
        <AdminLayout title={t('Translators')}>
            <div className="space-y-4">
                <div className={`${card} space-y-2 p-4 text-sm`}>
                    <p>
                        {t(
                            'Texts go to the first service in the list that is set up, switched on and within its daily limit. If it fails or the limit is used up, the next one takes over.',
                        )}
                    </p>
                    <p className="text-muted-foreground">
                        {t('Keys are written into the .env file on the server, never here. After changing .env run: php artisan optimize')}
                    </p>
                </div>

                <div className={`${card} divide-y dark:divide-neutral-800`}>
                    {rows.map((row, index) => {
                        const result = checks[row.id];
                        return (
                            <div key={row.id} className={`space-y-2 p-3 ${row.on && row.ready ? '' : 'opacity-70'}`}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="flex flex-col">
                                        <button
                                            type="button"
                                            aria-label={t('Up')}
                                            disabled={index === 0}
                                            className="text-muted-foreground rounded p-0.5 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-neutral-800"
                                            onClick={() => move(index, -1)}
                                        >
                                            <ArrowUp className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            aria-label={t('Down')}
                                            disabled={index === rows.length - 1}
                                            className="text-muted-foreground rounded p-0.5 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-neutral-800"
                                            onClick={() => move(index, 1)}
                                        >
                                            <ArrowDown className="size-4" />
                                        </button>
                                    </div>
                                    <span className="text-muted-foreground w-5 text-center text-sm">{index + 1}</span>
                                    <input
                                        type="checkbox"
                                        className="accent-brand size-4"
                                        checked={row.on}
                                        aria-label={t('On')}
                                        onChange={(e) => set(index, { on: e.target.checked })}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold">{row.label}</span>
                                            <Badge tone={price[row.price].tone}>{price[row.price].text}</Badge>
                                            {row.ready ? (
                                                <Badge tone="green">{t('Set up')}</Badge>
                                            ) : (
                                                <Badge tone="red">{t('Needs :key in .env', { key: row.needs.join(', ') })}</Badge>
                                            )}
                                        </div>
                                        <div className="text-muted-foreground text-xs">{t(row.note)}</div>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm whitespace-nowrap">
                                        {t('Daily limit')}
                                        <input
                                            type="number"
                                            min={0}
                                            className={`${field} w-28`}
                                            value={row.daily_chars}
                                            onChange={(e) => set(index, { daily_chars: Math.max(0, Number(e.target.value) || 0) })}
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        disabled={!row.ready || result === 'busy'}
                                        className="text-muted-foreground inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-sm hover:bg-slate-100 disabled:opacity-40 dark:hover:bg-neutral-800"
                                        onClick={() => check(row.id)}
                                    >
                                        <FlaskConical className="size-4" /> {t('Check')}
                                    </button>
                                </div>
                                <div className="text-muted-foreground ps-14 text-xs">
                                    {t('Used today: :used characters', { used: row.used_today.toLocaleString() })}
                                    {row.daily_chars > 0 ? ` / ${row.daily_chars.toLocaleString()}` : ` · ${t('no limit')}`}
                                </div>
                                {result && result !== 'busy' && (
                                    <p dir="auto" className={`ps-14 text-sm ${result.ok ? '' : 'text-red-600'}`}>
                                        {result.text}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-sm">
                        {t('Check translates into')}
                        <select className={field} value={checkLocale} onChange={(e) => setCheckLocale(e.target.value)}>
                            {languages
                                .filter((l) => l.code !== 'en')
                                .map((l) => (
                                    <option key={l.code} value={l.code}>
                                        {l.native_name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <button
                        type="button"
                        disabled={!changed || saving}
                        onClick={save}
                        className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white disabled:opacity-40"
                    >
                        {t('Save')}
                    </button>
                </div>
            </div>
        </AdminLayout>
    );
}
