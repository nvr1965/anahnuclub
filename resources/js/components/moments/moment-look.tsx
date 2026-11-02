import { type MomentItem } from '@/types';
import { type CSSProperties } from 'react';

/** Backgrounds of a moment of words; on a photo or video the same colour sits behind the words. */
export const BACKGROUNDS: Record<string, string> = {
    brand: 'linear-gradient(160deg,#117892,#0b5a6e)',
    sunset: 'linear-gradient(160deg,#f97316,#db2777)',
    ocean: 'linear-gradient(160deg,#0ea5e9,#4338ca)',
    forest: 'linear-gradient(160deg,#65a30d,#166534)',
    night: 'linear-gradient(160deg,#334155,#0f172a)',
    rose: 'linear-gradient(160deg,#f472b6,#9333ea)',
};

export const MOMENT_SECONDS = 30;

/** How long a photo or a few words stay on screen before the next moment. */
export const STILL_MS = 6000;

const place: Record<MomentItem['text_position'], CSSProperties> = {
    top: { top: '14%' },
    center: { top: '50%', transform: 'translateY(-50%)' },
    bottom: { bottom: '16%' },
};

/** The words of a moment over its picture, or filling a coloured card. */
export function MomentWords({
    text,
    kind,
    position,
    background,
}: {
    text: string;
    kind: MomentItem['kind'];
    position: MomentItem['text_position'];
    background: string | null;
}) {
    if (kind === 'text') {
        return (
            <div
                className="absolute inset-0 flex items-center justify-center p-8"
                style={{ background: BACKGROUNDS[background ?? 'brand'] ?? BACKGROUNDS.brand }}
            >
                <p dir="auto" className="text-center text-2xl leading-snug font-bold break-words whitespace-pre-line text-white sm:text-3xl">
                    {text}
                </p>
            </div>
        );
    }

    return (
        <div className="pointer-events-none absolute inset-x-4 flex justify-center" style={place[position] ?? place.bottom}>
            <p
                dir="auto"
                className="max-w-full rounded-xl px-3 py-1.5 text-center text-lg leading-snug font-semibold break-words whitespace-pre-line text-white shadow-lg"
                style={{ background: background ? BACKGROUNDS[background] : 'rgba(0,0,0,.55)' }}
            >
                {text}
            </p>
        </div>
    );
}
