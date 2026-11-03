import { countryFlagUrl, countryName, countryOptions } from '@/lib/countries';
import { useT } from '@/lib/i18n';
import { ChevronDown, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

/** A country's flag as a small picture. */
export function CountryFlag({ code, className = '' }: { code: string; className?: string }) {
    const src = countryFlagUrl(code);
    return src ? (
        <img
            src={src}
            alt=""
            className={`inline-block h-3.5 w-[18px] shrink-0 rounded-[2px] object-cover shadow-[0_0_0_1px_rgba(0,0,0,0.08)] ${className}`}
        />
    ) : null;
}

/** A country field you can type into: the list narrows to names that match, in the reader's language or English. */
export function CountryPicker({
    value,
    onChange,
    locale,
    className,
}: {
    value: string;
    onChange: (code: string) => void;
    locale: string;
    className: string;
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const list = useRef<HTMLUListElement>(null);

    const options = useMemo(
        () =>
            countryOptions(locale).map(([code, label]) => ({
                code,
                label,
                names: [countryName(code, locale), countryName(code, 'en')].map((n) => n.toLocaleLowerCase(locale)),
            })),
        [locale],
    );
    const needle = query.trim().toLocaleLowerCase(locale);
    // Names that start with the typed letters first, then a later word that does, then any match.
    const rank = (names: string[]) =>
        names.some((n) => n.startsWith(needle))
            ? 0
            : names.some((n) => n.split(/[\s-]+/).some((w) => w.startsWith(needle)))
              ? 1
              : names.some((n) => n.includes(needle))
                ? 2
                : 3;
    const shown = needle
        ? options
              .map((o) => ({ ...o, rank: rank(o.names) }))
              .filter((o) => o.rank < 3)
              .sort((a, b) => a.rank - b.rank)
        : options;

    const pick = (code: string) => {
        onChange(code);
        setQuery('');
        setOpen(false);
    };
    const move = (by: number) => {
        const next = Math.max(0, Math.min(shown.length - 1, active + by));
        setActive(next);
        list.current?.children[next]?.scrollIntoView({ block: 'nearest' });
    };

    return (
        <div className="relative">
            <input
                type="text"
                role="combobox"
                aria-expanded={open}
                className={`${className} pe-14 ${value && !open ? 'ps-10' : ''}`}
                placeholder={value ? '' : t('Start typing a country')}
                value={open ? query : value ? countryName(value, locale) : ''}
                onFocus={() => {
                    setOpen(true);
                    setActive(0);
                }}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setActive(0);
                    setOpen(true);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setOpen(true);
                        move(1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        move(-1);
                    } else if (e.key === 'Enter' && open && shown[active]) {
                        e.preventDefault();
                        pick(shown[active].code);
                    }
                    if (e.key === 'Escape') setOpen(false);
                }}
            />
            {value && !open && (
                <span className="pointer-events-none absolute inset-y-0 start-3 flex items-center">
                    <CountryFlag code={value} />
                </span>
            )}
            <div className="text-muted-foreground pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1">
                {value && !open && (
                    <button
                        type="button"
                        aria-label={t('Clear')}
                        className="pointer-events-auto rounded p-0.5 hover:bg-slate-100 dark:hover:bg-neutral-800"
                        onClick={() => onChange('')}
                    >
                        <X className="size-4" />
                    </button>
                )}
                <ChevronDown className="size-4" />
            </div>
            {open && (
                <ul
                    ref={list}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900"
                >
                    {shown.length === 0 && <li className="text-muted-foreground px-3 py-2 text-sm">{t('Nothing found.')}</li>}
                    {shown.map((o, i) => (
                        <li
                            key={o.code}
                            role="option"
                            aria-selected={o.code === value}
                            onMouseDown={(e) => {
                                e.preventDefault();
                                pick(o.code);
                            }}
                            onMouseEnter={() => setActive(i)}
                            className={`cursor-pointer px-3 py-1.5 text-[15px] ${i === active ? 'bg-slate-100 dark:bg-neutral-800' : ''} ${o.code === value ? 'font-semibold' : ''}`}
                        >
                            <span className="flex items-center gap-2">
                                <CountryFlag code={o.code} />
                                {o.label}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
