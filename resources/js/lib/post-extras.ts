import { useT } from '@/lib/i18n';

/** How the author feels: "Anna — 😊 feeling happy". Keys match Post::FEELINGS. */
export const FEELINGS = {
    happy: '😊',
    loved: '🥰',
    blessed: '😇',
    grateful: '🙏',
    excited: '🤩',
    relaxed: '😌',
    proud: '😎',
    hopeful: '🌟',
    in_love: '😍',
    festive: '🥳',
    thoughtful: '🤔',
    tired: '😴',
    sad: '😢',
    sick: '🤒',
    angry: '😠',
    nostalgic: '🥹',
} as const;
export type Feeling = keyof typeof FEELINGS;

/** Life events, a card above the words. Keys match Post::LIFE_EVENTS. */
export const LIFE_EVENTS = {
    new_job: '💼',
    graduated: '🎓',
    moved: '🏠',
    aliyah: '✈️',
    engaged: '💍',
    married: '💒',
    baby: '👶',
    travel: '🧳',
    retired: '🌴',
    anniversary: '🎂',
} as const;
export type LifeEvent = keyof typeof LIFE_EVENTS;

/** The words for each feeling and life event, in the reader's language. */
export function useExtrasLabels() {
    const t = useT();
    const feelings: Record<Feeling, string> = {
        happy: t('happy'),
        loved: t('loved'),
        blessed: t('blessed'),
        grateful: t('grateful'),
        excited: t('excited'),
        relaxed: t('relaxed'),
        proud: t('proud'),
        hopeful: t('hopeful'),
        in_love: t('in love'),
        festive: t('festive'),
        thoughtful: t('thoughtful'),
        tired: t('tired'),
        sad: t('sad'),
        sick: t('sick'),
        angry: t('angry'),
        nostalgic: t('nostalgic'),
    };
    const lifeEvents: Record<LifeEvent, string> = {
        new_job: t('New job'),
        graduated: t('Graduated'),
        moved: t('Moved to a new place'),
        aliyah: t('Made aliyah'),
        engaged: t('Got engaged'),
        married: t('Got married'),
        baby: t('Welcomed a baby'),
        travel: t('Went on a trip'),
        retired: t('Retired'),
        anniversary: t('Anniversary'),
    };
    return { feelings, lifeEvents };
}

/** The place on a map, for "is at …". */
export const mapUrl = (place: string) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(place)}`;
