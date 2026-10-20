import { Badge, card } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FlaskConical, KeyRound } from 'lucide-react';
import { useEffect, useState } from 'react';

/** What the server decides about a service, kept apart from the order and switches being edited. */
const pick = (row?: Translator) => (row ? { ready: row.ready, fields: row.fields, used_today: row.used_today, daily_chars: row.daily_chars } : {});

interface Translator {
    id: string;
    label: string;
    price: 'free' | 'free_tier' | 'paid';
    note: string;
    ready: boolean;
    needs: string[];
    fields: { path: string; env: string; secret: boolean; value: string; source: 'admin' | 'env' | null }[];
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
    const [editing, setEditing] = useState<string | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const changed =
        JSON.stringify(rows.map(({ id, on, daily_chars }) => [id, on, daily_chars])) !==
        JSON.stringify(translators.map(({ id, on, daily_chars }) => [id, on, daily_chars]));

    // After a save the page comes back with fresh "set up" marks and limits.
    useEffect(() => setRows((all) => all.map((row) => ({ ...row, ...pick(translators.find((r) => r.id === row.id)) }))), [translators]);

    const saveKeys = (id: string, patch: Record<string, string>) =>
        router.put(
            route('admin.translators.keys', id),
            { values: patch },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setValues({});
                    setEditing(null);
                },
            },
        );

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
                        {t(
                            'Keys can be entered here or written into the .env file on the server. A key entered here is stored encrypted and takes the place of the one in .env.',
                        )}
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
                                            {row.ready ? <Badge tone="green">{t('Set up')}</Badge> : <Badge tone="red">{t('Needs a key')}</Badge>}
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
                                    {row.fields.length > 0 && (
                                        <button
                                            type="button"
                                            className="text-muted-foreground inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-neutral-800"
                                            onClick={() => {
                                                setValues({});
                                                setEditing(editing === row.id ? null : row.id);
                                            }}
                                        >
                                            <KeyRound className="size-4" /> {t('Key')}
                                        </button>
                                    )}
                                </div>
                                {(editing === row.id || (!row.ready && row.on)) && row.fields.length > 0 && (
                                    <form
                                        className="space-y-2 rounded-md bg-slate-50 p-3 sm:ms-14 dark:bg-neutral-800/50"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            const patch = Object.fromEntries(Object.entries(values).filter(([, v]) => v.trim() !== ''));
                                            if (Object.keys(patch).length) saveKeys(row.id, patch);
                                        }}
                                    >
                                        {row.fields.map((f) => (
                                            <div key={f.path} className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                                    <span className="font-mono text-xs">{f.env}</span>
                                                    {f.source === 'admin' && <Badge tone="green">{t('Saved here')}</Badge>}
                                                    {f.source === 'env' && <Badge tone="gray">{t('From .env')}</Badge>}
                                                    {f.source === 'admin' && (
                                                        <button
                                                            type="button"
                                                            className="text-xs text-red-600 hover:underline"
                                                            onClick={() => saveKeys(row.id, { [f.path]: '' })}
                                                        >
                                                            {t('Remove')}
                                                        </button>
                                                    )}
                                                </div>
                                                <input
                                                    type={f.secret ? 'password' : 'text'}
                                                    autoComplete="off"
                                                    className={`${field} w-full font-mono`}
                                                    placeholder={f.value || t('Not set')}
                                                    value={values[f.path] ?? ''}
                                                    onChange={(e) => setValues((all) => ({ ...all, [f.path]: e.target.value }))}
                                                />
                                            </div>
                                        ))}
                                        <button
                                            type="submit"
                                            disabled={!Object.values(values).some((v) => v.trim() !== '')}
                                            className="bg-brand hover:bg-brand-dark rounded-md px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-40"
                                        >
                                            {t('Save key')}
                                        </button>
                                    </form>
                                )}
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
