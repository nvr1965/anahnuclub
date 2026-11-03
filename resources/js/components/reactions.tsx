import { useT } from '@/lib/i18n';
import { type Reaction } from '@/types';
import { ThumbsUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** Facebook's seven reactions, with the label and colour the button takes once one is chosen. */
export const REACTIONS: { type: Reaction; emoji: string; label: string; color: string }[] = [
    { type: 'like', emoji: '👍', label: 'Like', color: 'text-brand' },
    { type: 'love', emoji: '❤️', label: 'Love', color: 'text-rose-600' },
    { type: 'care', emoji: '🥰', label: 'Care', color: 'text-amber-500' },
    { type: 'haha', emoji: '😆', label: 'Haha', color: 'text-amber-500' },
    { type: 'wow', emoji: '😮', label: 'Wow', color: 'text-amber-500' },
    { type: 'sad', emoji: '😢', label: 'Sad', color: 'text-amber-500' },
    { type: 'angry', emoji: '😡', label: 'Angry', color: 'text-orange-600' },
];
const byType = Object.fromEntries(REACTIONS.map((r) => [r.type, r]));

/** The faces of the most left reactions, overlapping, before the count. */
export function ReactionFaces({ types }: { types: Reaction[] }) {
    return (
        <span className="inline-flex">
            {types.map((type, i) => (
                <span
                    key={type}
                    className="-ms-1 inline-flex size-[20px] items-center justify-center rounded-full bg-white text-[14px] leading-none ring-2 ring-white first:ms-0 dark:bg-neutral-900 dark:ring-neutral-900"
                    style={{ zIndex: types.length - i }}
                >
                    {byType[type]?.emoji}
                </span>
            ))}
        </span>
    );
}

/**
 * The Like button: a tap likes or takes the reaction back; resting the pointer on it
 * (or holding a finger on it) opens the row of faces to pick another reaction.
 */
export function ReactionButton({
    mine,
    disabled,
    className,
    onReact,
}: {
    mine: Reaction | null;
    disabled?: boolean;
    className: string;
    onReact: (type: Reaction | null) => void;
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const held = useRef(false);
    const chosen = mine ? byType[mine] : null;

    const later = (fn: () => void, ms: number) => {
        clearTimeout(timer.current);
        timer.current = setTimeout(fn, ms);
    };
    useEffect(() => () => clearTimeout(timer.current), []);

    const pick = (type: Reaction) => {
        setOpen(false);
        onReact(type === mine ? null : type);
    };

    return (
        <div
            className="relative flex"
            onMouseEnter={() => !disabled && later(() => setOpen(true), 450)}
            onMouseLeave={() => later(() => setOpen(false), 300)}
        >
            {open && (
                <div
                    role="menu"
                    className="absolute start-0 bottom-full z-20 mb-1 flex gap-0.5 rounded-full bg-white px-1.5 py-1 shadow-lg ring-1 ring-black/5 dark:bg-neutral-800"
                >
                    {REACTIONS.map((r) => (
                        <button
                            key={r.type}
                            type="button"
                            role="menuitem"
                            title={t(r.label)}
                            aria-label={t(r.label)}
                            onClick={() => pick(r.type)}
                            className={`origin-bottom rounded-full p-1 text-[28px] leading-none transition-transform hover:-translate-y-1 hover:scale-125 max-sm:text-[26px] ${mine === r.type ? 'bg-slate-100 dark:bg-neutral-700' : ''}`}
                        >
                            {r.emoji}
                        </button>
                    ))}
                </div>
            )}
            <button
                type="button"
                disabled={disabled}
                className={`${className} w-full ${chosen ? chosen.color : 'text-muted-foreground'} select-none`}
                onClick={() => {
                    if (held.current) return void (held.current = false);
                    setOpen(false);
                    onReact(mine ? null : 'like');
                }}
                onTouchStart={() =>
                    later(() => {
                        held.current = true;
                        setOpen(true);
                    }, 450)
                }
                onTouchEnd={() => clearTimeout(timer.current)}
                onContextMenu={(e) => e.preventDefault()}
            >
                {chosen && chosen.type !== 'like' ? (
                    <span className="text-[18px] leading-none">{chosen.emoji}</span>
                ) : (
                    <ThumbsUp className={`size-5 ${chosen ? 'fill-current' : ''}`} />
                )}{' '}
                {t(chosen?.label ?? 'Like')}
            </button>
        </div>
    );
}
