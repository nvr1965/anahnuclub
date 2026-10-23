/** "Chrome · Android" from a browser's User-Agent line; good enough to tell one's devices apart. */
export function describeDevice(ua: string | null): string {
    if (!ua) return '';
    const browser = [
        [/Edg(e|A|iOS)?\//, 'Edge'],
        [/OPR\/|Opera/, 'Opera'],
        [/SamsungBrowser\//, 'Samsung Internet'],
        [/YaBrowser\//, 'Yandex'],
        [/FBAN|FBAV/, 'Facebook'],
        [/Instagram/, 'Instagram'],
        [/Firefox\/|FxiOS\//, 'Firefox'],
        [/Chrome\/|CriOS\//, 'Chrome'],
        [/Safari\//, 'Safari'],
    ].find(([re]) => (re as RegExp).test(ua))?.[1] as string | undefined;
    const os = [
        [/iPhone|iPod/, 'iPhone'],
        [/iPad/, 'iPad'],
        [/Android/, 'Android'],
        [/Windows/, 'Windows'],
        [/Mac OS X|Macintosh/, 'macOS'],
        [/CrOS/, 'ChromeOS'],
        [/Linux/, 'Linux'],
    ].find(([re]) => (re as RegExp).test(ua))?.[1] as string | undefined;

    return [browser, os].filter(Boolean).join(' · ') || ua.slice(0, 60);
}
