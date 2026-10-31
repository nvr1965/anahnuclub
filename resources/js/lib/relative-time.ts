const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

/** "4 hours ago" in the interface language. */
export function relativeTime(iso: string | null, locale: string): string {
    if (!iso) return '';
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit);
    }
    return format.format(0, 'minute');
}

/** Facebook's short age beside a notice or a chat: "7 h", "2 wk", in the interface language. */
export function shortAgo(iso: string | null, locale: string): string {
    if (!iso) return '';
    const seconds = Math.max(60, (Date.now() - new Date(iso).getTime()) / 1000);
    for (const [unit, size] of units) {
        if (seconds >= size) {
            try {
                return new Intl.NumberFormat(locale, { style: 'unit', unit, unitDisplay: 'narrow' }).format(Math.floor(seconds / size));
            } catch {
                return relativeTime(iso, locale);
            }
        }
    }
    return '';
}
