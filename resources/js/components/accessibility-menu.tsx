/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { type AccessibilitySettings, type Palette, DEFAULT_SETTINGS, TEXT_STEPS, isDefault, loadSettings, saveSettings } from '@/lib/accessibility';
import { useT } from '@/lib/i18n';
import { AArrowDown, AArrowUp, Accessibility, Contrast, Link2, MousePointer2, Pause, RotateCcw, Rows3, Type } from 'lucide-react';
import { type ReactNode, useState } from 'react';

/**
 * The accessibility menu (IS 5568): a round button at the bottom of every page opens it. Text size,
 * a palette (high contrast, dark contrast, grey), underlined links, a plain readable font, wider line
 * spacing, no animations and a big cursor; with a link to the accessibility statement.
 */
export function AccessibilityMenu() {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [settings, setSettings] = useState<AccessibilitySettings>(() => loadSettings());

    const change = (patch: Partial<AccessibilitySettings>) => {
        const next = { ...settings, ...patch };
        setSettings(next);
        saveSettings(next);
    };

    const palettes: [Palette, string][] = [
        ['normal', t('Usual colours')],
        ['contrast', t('High contrast')],
        ['dark-contrast', t('Dark high contrast')],
        ['gray', t('Black and white')],
    ];
    const toggles: [keyof AccessibilitySettings, string, ReactNode][] = [
        ['links', t('Underline links'), <Link2 key="links" className="size-5" />],
        ['readable', t('Readable font'), <Type key="readable" className="size-5" />],
        ['spacing', t('Wider line spacing'), <Rows3 key="spacing" className="size-5" />],
        ['still', t('Stop animations'), <Pause key="still" className="size-5" />],
        ['cursor', t('Big cursor'), <MousePointer2 key="cursor" className="size-5" />],
    ];
    const tile = (on: boolean) =>
        `flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-start text-sm font-medium transition ${
            on
                ? 'border-brand bg-brand-soft text-brand'
                : 'border-slate-300 bg-white hover:bg-slate-100 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800'
        }`;
    const percent = Math.round(TEXT_STEPS[settings.text] * 100);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label={t('Accessibility')}
                title={t('Accessibility')}
                className="a11y-button fixed start-3 bottom-20 z-40 flex size-11 items-center justify-center rounded-full bg-[#1a56db] text-white shadow-lg ring-2 ring-white hover:bg-[#1e429f] sm:bottom-4 dark:ring-neutral-900 print:hidden"
            >
                <Accessibility className="size-6" aria-hidden="true" />
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] gap-0 overflow-y-auto p-0 sm:max-w-md">
                    <div className="border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                        <DialogTitle className="flex items-center justify-center gap-2 text-lg">
                            <Accessibility className="size-5" aria-hidden="true" /> {t('Accessibility')}
                        </DialogTitle>
                        <DialogDescription className="sr-only">{t('Settings that make the site easier to read and use.')}</DialogDescription>
                    </div>
                    <div className="space-y-4 p-4">
                        <section aria-labelledby="a11y-text">
                            <h3 id="a11y-text" className="mb-2 text-sm font-semibold">
                                {t('Text size')}
                            </h3>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    className={tile(false)}
                                    onClick={() => change({ text: Math.max(0, settings.text - 1) })}
                                    disabled={settings.text === 0}
                                    aria-label={t('Smaller text')}
                                >
                                    <AArrowDown className="size-5" aria-hidden="true" />
                                </button>
                                <output className="min-w-16 text-center font-semibold" aria-live="polite">
                                    {percent}%
                                </output>
                                <button
                                    type="button"
                                    className={tile(false)}
                                    onClick={() => change({ text: Math.min(TEXT_STEPS.length - 1, settings.text + 1) })}
                                    disabled={settings.text === TEXT_STEPS.length - 1}
                                    aria-label={t('Larger text')}
                                >
                                    <AArrowUp className="size-5" aria-hidden="true" />
                                </button>
                            </div>
                        </section>
                        <section aria-labelledby="a11y-palette">
                            <h3 id="a11y-palette" className="mb-2 flex items-center gap-2 text-sm font-semibold">
                                <Contrast className="size-4" aria-hidden="true" /> {t('Colours')}
                            </h3>
                            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="a11y-palette">
                                {palettes.map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        role="radio"
                                        aria-checked={settings.palette === value}
                                        className={tile(settings.palette === value)}
                                        onClick={() => change({ palette: value })}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </section>
                        <section aria-labelledby="a11y-aids">
                            <h3 id="a11y-aids" className="mb-2 text-sm font-semibold">
                                {t('Reading and moving')}
                            </h3>
                            <div className="grid grid-cols-2 gap-2">
                                {toggles.map(([key, label, icon]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        aria-pressed={Boolean(settings[key])}
                                        className={tile(Boolean(settings[key]))}
                                        onClick={() => change({ [key]: !settings[key] })}
                                    >
                                        {icon}
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </section>
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-neutral-800">
                            <button type="button" className={tile(false)} onClick={() => change(DEFAULT_SETTINGS)} disabled={isDefault(settings)}>
                                <RotateCcw className="size-4" aria-hidden="true" /> {t('Reset')}
                            </button>
                            <a href="/p/accessibility" className="text-brand text-sm font-medium underline">
                                {t('Accessibility statement')}
                            </a>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
