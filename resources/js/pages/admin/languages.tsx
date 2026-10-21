import { Badge, card, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { Languages, Plus, RefreshCw, Repeat, Trash2 } from 'lucide-react';
import { useEffect } from 'react';

interface Row {
    code: string;
    name: string;
    native_name: string;
    rtl: boolean;
    enabled: boolean;
    missing: number;
    built_in: boolean;
    base: boolean;
    paused: string | null;
}

/** Common choices; anything else can be typed in by hand. Languages DeepL lacks need the Claude key. */
const PRESETS: [code: string, name: string, native: string, rtl?: boolean][] = [
    ['de', 'German', 'Deutsch'],
    ['fr', 'French', 'Français'],
    ['es', 'Spanish', 'Español'],
    ['it', 'Italian', 'Italiano'],
    ['pt', 'Portuguese', 'Português'],
    ['uk', 'Ukrainian', 'Українська'],
    ['uz', 'Uzbek', 'Oʻzbekcha'],
    ['kk', 'Kazakh', 'Қазақша'],
    ['ky', 'Kyrgyz', 'Кыргызча'],
    ['tg', 'Tajik', 'Тоҷикӣ'],
    ['az', 'Azerbaijani', 'Azərbaycanca'],
    ['ka', 'Georgian', 'ქართული'],
    ['hy', 'Armenian', 'Հայերեն'],
    ['fa', 'Persian', 'فارسی', true],
    ['pl', 'Polish', 'Polski'],
    ['tr', 'Turkish', 'Türkçe'],
    ['nl', 'Dutch', 'Nederlands'],
    ['ro', 'Romanian', 'Română'],
    ['cs', 'Czech', 'Čeština'],
    ['sr', 'Serbian (Cyrillic)', 'Српски'],
    ['hr', 'Croatian', 'Hrvatski'],
    ['bg', 'Bulgarian', 'Български'],
    ['el', 'Greek', 'Ελληνικά'],
    ['hu', 'Hungarian', 'Magyar'],
    ['sv', 'Swedish', 'Svenska'],
    ['fi', 'Finnish', 'Suomi'],
    ['da', 'Danish', 'Dansk'],
    ['nb', 'Norwegian', 'Norsk'],
    ['lt', 'Lithuanian', 'Lietuvių'],
    ['lv', 'Latvian', 'Latviešu'],
    ['et', 'Estonian', 'Eesti'],
    ['sk', 'Slovak', 'Slovenčina'],
    ['sl', 'Slovenian', 'Slovenščina'],
    ['id', 'Indonesian', 'Bahasa Indonesia'],
    ['ja', 'Japanese', '日本語'],
    ['ko', 'Korean', '한국어'],
    ['zh', 'Chinese', '中文'],
    ['vi', 'Vietnamese', 'Tiếng Việt'],
    ['th', 'Thai', 'ไทย'],
    ['hi', 'Hindi', 'हिन्दी'],
];

export default function AdminLanguages({ languages, total, can_translate }: { languages: Row[]; total: number; can_translate: boolean }) {
    const t = useT();
    const form = useForm<{ code: string; name: string; native_name: string; rtl: boolean }>({ code: '', name: '', native_name: '', rtl: false });
    const busy = languages.some((l) => l.missing > 0 && !l.enabled && !l.built_in);
    const presets = PRESETS.filter(([code]) => !languages.some((l) => l.code === code));

    // While a new language is being translated, look again now and then to show the progress.
    useEffect(() => {
        if (!busy) return;
        const timer = window.setInterval(() => router.reload({ only: ['languages'] }), 10000);
        return () => window.clearInterval(timer);
    }, [busy]);

    const pick = (code: string) => {
        const preset = PRESETS.find((p) => p[0] === code);
        form.setData(
            preset
                ? { code: preset[0], name: preset[1], native_name: preset[2], rtl: Boolean(preset[3]) }
                : { code: '', name: '', native_name: '', rtl: false },
        );
    };

    const field =
        'focus:border-brand rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';

    return (
        <AdminLayout title={t('Languages')}>
            <div className="space-y-4">
                <div className={`${card} divide-y dark:divide-neutral-800`}>
                    {languages.map((language) => (
                        <div key={language.code} className="flex flex-wrap items-center gap-3 p-3">
                            <input
                                type="checkbox"
                                className="accent-brand size-4"
                                checked={language.enabled}
                                disabled={language.base}
                                title={language.base ? t('The base language stays on') : undefined}
                                aria-label={t('On')}
                                onChange={() => send('patch', route('admin.languages.update', language.code), { enabled: !language.enabled })}
                            />
                            <div className="min-w-0 flex-1">
                                <span className="font-medium">{language.native_name}</span>{' '}
                                <span className="text-muted-foreground text-sm">
                                    {language.name} · {language.code}
                                </span>
                                {language.missing > 0 && (
                                    <div className="text-muted-foreground text-xs">
                                        {t('Interface translated: :done of :total', { done: total - language.missing, total })}
                                    </div>
                                )}
                                {language.missing > 0 && language.paused && (
                                    <div className="text-xs break-words text-amber-700 dark:text-amber-400">
                                        {t('Paused, tried again every hour: :error', { error: language.paused })}
                                    </div>
                                )}
                            </div>
                            {language.base && <Badge tone="brand">{t('Base language')}</Badge>}
                            {language.rtl && <Badge>RTL</Badge>}
                            {!language.enabled && language.missing > 0 && !language.built_in && <Badge tone="brand">{t('Translating…')}</Badge>}
                            {language.missing > 0 && can_translate && (
                                <button
                                    type="button"
                                    title={t('Translate the missing interface strings')}
                                    className="text-muted-foreground rounded-md p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    onClick={() => send('post', route('admin.languages.refill', language.code))}
                                >
                                    <RefreshCw className="size-4" />
                                </button>
                            )}
                            <Link
                                href={route('admin.languages.texts', language.code)}
                                title={t('Interface texts')}
                                aria-label={t('Interface texts')}
                                className="text-muted-foreground rounded-md p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                            >
                                <Languages className="size-4" />
                            </Link>
                            {!language.built_in && can_translate && language.missing === 0 && (
                                <button
                                    type="button"
                                    title={t('Translate the interface again (texts corrected by hand stay)')}
                                    aria-label={t('Translate the interface again (texts corrected by hand stay)')}
                                    className="text-muted-foreground rounded-md p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    onClick={() =>
                                        window.confirm(t('Translate the interface again (texts corrected by hand stay)') + '?') &&
                                        send('post', route('admin.languages.retranslate', language.code))
                                    }
                                >
                                    <Repeat className="size-4" />
                                </button>
                            )}
                            {!language.built_in && (
                                <button
                                    type="button"
                                    aria-label={t('Delete')}
                                    className="text-muted-foreground rounded-md p-2 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                    onClick={() =>
                                        window.confirm(t('Delete the language :name?', { name: language.native_name })) &&
                                        send('delete', route('admin.languages.destroy', language.code))
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                <form
                    className={`${card} space-y-3 p-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.languages.store'), { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    <h2 className="font-semibold">{t('Add a language')}</h2>
                    <p className="text-muted-foreground text-sm">
                        {can_translate
                            ? t(
                                  'The interface and the site pages are translated automatically. The language switches on by itself when the translation is ready.',
                              )
                            : t('No translation service is set up, so the interface of a new language stays in English until it is translated.')}
                    </p>
                    <select
                        className={`${field} w-full`}
                        value={PRESETS.some((p) => p[0] === form.data.code) ? form.data.code : ''}
                        onChange={(e) => pick(e.target.value)}
                    >
                        <option value="">{t('Choose a language or fill in the fields below')}</option>
                        {presets.map(([code, name, native]) => (
                            <option key={code} value={code}>
                                {native} · {name}
                            </option>
                        ))}
                    </select>
                    <div className="grid gap-2 sm:grid-cols-[100px_1fr_1fr]">
                        <input
                            className={field}
                            placeholder={t('Code')}
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toLowerCase().trim())}
                        />
                        <input
                            className={field}
                            placeholder={t('Name in English')}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        <input
                            dir="auto"
                            className={field}
                            placeholder={t('Name in the language itself')}
                            value={form.data.native_name}
                            onChange={(e) => form.setData('native_name', e.target.value)}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="accent-brand size-4"
                            checked={form.data.rtl}
                            onChange={(e) => form.setData('rtl', e.target.checked)}
                        />
                        {t('Written right to left')}
                    </label>
                    {Object.values(form.errors).length > 0 && <p className="text-sm text-red-600">{Object.values(form.errors)[0]}</p>}
                    <div className="flex justify-end">
                        <button
                            className="bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-md px-5 py-2 text-sm font-semibold text-white disabled:opacity-40"
                            disabled={form.processing || !form.data.code || !form.data.name.trim() || !form.data.native_name.trim()}
                        >
                            <Plus className="size-4" /> {t('Add language')}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
