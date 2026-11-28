/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

/**
 * The visitor's accessibility settings (IS 5568 / WCAG 2.0 AA): text size, a palette, and a few aids.
 * Kept in this browser only and put on <html> as classes before the page draws (also by the inline
 * script in app.blade.php), so the page never flashes in the wrong look.
 */
export type Palette = 'normal' | 'contrast' | 'dark-contrast' | 'gray';

export interface AccessibilitySettings {
    /** 0 is the usual size; each step makes all text larger (1.125, 1.25, 1.5, 1.75 times). */
    text: number;
    palette: Palette;
    links: boolean;
    readable: boolean;
    spacing: boolean;
    still: boolean;
    cursor: boolean;
}

export const TEXT_STEPS = [1, 1.125, 1.25, 1.5, 1.75];

export const DEFAULT_SETTINGS: AccessibilitySettings = {
    text: 0,
    palette: 'normal',
    links: false,
    readable: false,
    spacing: false,
    still: false,
    cursor: false,
};

const KEY = 'a11y';

export function loadSettings(): AccessibilitySettings {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) ?? 'null');
        return saved && typeof saved === 'object' ? { ...DEFAULT_SETTINGS, ...saved } : { ...DEFAULT_SETTINGS };
    } catch {
        return { ...DEFAULT_SETTINGS };
    }
}

export function saveSettings(settings: AccessibilitySettings) {
    try {
        localStorage.setItem(KEY, JSON.stringify(settings));
    } catch {
        // Without storage the settings last until the page is closed.
    }
    applySettings(settings);
}

export function isDefault(settings: AccessibilitySettings) {
    return (Object.keys(DEFAULT_SETTINGS) as (keyof AccessibilitySettings)[]).every((k) => settings[k] === DEFAULT_SETTINGS[k]);
}

export function applySettings(settings: AccessibilitySettings) {
    const html = document.documentElement;
    const step = Math.max(0, Math.min(TEXT_STEPS.length - 1, Math.round(settings.text)));
    html.style.fontSize = step ? `${TEXT_STEPS[step] * 100}%` : '';
    // The light high-contrast palette is drawn on white: the dark theme gives way to it while it is on.
    let dark = false;
    try {
        const appearance = localStorage.getItem('appearance') ?? 'system';
        dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    } catch {
        dark = html.classList.contains('dark');
    }
    html.classList.toggle('dark', settings.palette === 'contrast' ? false : dark);
    html.classList.toggle('a11y-contrast', settings.palette === 'contrast');
    html.classList.toggle('a11y-dark-contrast', settings.palette === 'dark-contrast');
    html.classList.toggle('a11y-gray', settings.palette === 'gray');
    html.classList.toggle('a11y-links', settings.links);
    html.classList.toggle('a11y-readable', settings.readable);
    html.classList.toggle('a11y-spacing', settings.spacing);
    html.classList.toggle('a11y-still', settings.still);
    html.classList.toggle('a11y-cursor', settings.cursor);
}

export function initializeAccessibility() {
    applySettings(loadSettings());
}
