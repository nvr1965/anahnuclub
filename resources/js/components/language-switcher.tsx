import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Languages } from 'lucide-react';
import { useState } from 'react';

/** The country whose flag stands for a language, where it is not the same two letters. */
const COUNTRY: Record<string, string> = {
    ar: 'sa',
    cs: 'cz',
    da: 'dk',
    el: 'gr',
    en: 'gb',
    et: 'ee',
    fa: 'ir',
    he: 'il',
    hi: 'in',
    hy: 'am',
    ja: 'jp',
    ka: 'ge',
    kk: 'kz',
    ko: 'kr',
    ky: 'kg',
    nb: 'no',
    sl: 'si',
    sv: 'se',
    tg: 'tj',
    uk: 'ua',
    vi: 'vn',
    zh: 'cn',
};

/** A flag drawn by the system's emoji font; where there is none (Windows) it reads as the two letters. */
export function flag(code: string): string {
    const country = (code.split('-')[1] ?? COUNTRY[code] ?? code).toUpperCase();
    return /^[A-Z]{2}$/.test(country) ? String.fromCodePoint(...[...country].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65)) : '';
}

const choose = (code: string) => router.post(route('locale.update'), { locale: code }, { preserveScroll: true });

/**
 * The language of the site: a button with the flag and name that opens all languages as tiles
 * (with a search once there are many). Inside a menu, which closes on any click, a plain list is used.
 */
export function LanguageSwitcher({ className = '', compact = false }: { className?: string; compact?: boolean }) {
    const t = useT();
    const { locale, languages } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    if (compact) {
        return (
            <label className={`inline-flex items-center gap-2 text-sm ${className}`}>
                <Languages className="size-4 opacity-70" />
                <select aria-label={t('Language')} className="bg-transparent outline-none" value={locale} onChange={(e) => choose(e.target.value)}>
                    {languages.map((language) => (
                        <option key={language.code} value={language.code}>
                            {flag(language.code)} {language.native_name}
                        </option>
                    ))}
                </select>
            </label>
        );
    }

    const current = languages.find((l) => l.code === locale);
    const q = query.trim().toLowerCase();
    const sorted = [...languages].sort((a, b) => a.native_name.localeCompare(b.native_name));
    const shown = q ? sorted.filter((l) => `${l.native_name} ${l.code}`.toLowerCase().includes(q)) : sorted;

    return (
        <>
            <button
                type="button"
                aria-haspopup="dialog"
                onClick={() => setOpen(true)}
                className={`inline-flex items-center gap-2 text-sm ${className}`}
            >
                <span aria-hidden="true" className="text-base leading-none">
                    {flag(locale)}
                </span>
                <span>{current?.native_name ?? locale}</span>
                <ChevronDown className="size-3.5 opacity-60" />
            </button>
            <Dialog open={open} onOpenChange={(value) => (setOpen(value), setQuery(''))}>
                <DialogContent className="max-h-[85vh] overflow-hidden sm:max-w-xl">
                    <DialogTitle>{t('Language')}</DialogTitle>
                    {languages.length > 9 && (
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('Search')}
                            className="focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900"
                        />
                    )}
                    <div className="grid max-h-[60vh] grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                        {shown.map((language) => (
                            <button
                                key={language.code}
                                type="button"
                                aria-pressed={language.code === locale}
                                onClick={() => (setOpen(false), language.code !== locale && choose(language.code))}
                                className="aria-pressed:border-brand aria-pressed:bg-brand/5 flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-neutral-800 dark:hover:bg-neutral-800"
                            >
                                <span aria-hidden="true" className="text-2xl leading-none">
                                    {flag(language.code)}
                                </span>
                                <span dir={language.rtl ? 'rtl' : 'ltr'} className="min-w-0 truncate font-medium">
                                    {language.native_name}
                                </span>
                            </button>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
