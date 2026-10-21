/** Pictures shown when a member, group or event has none of its own (public/images/defaults, CC0). */
export const defaultImages = {
    user: '/images/defaults/user.svg',
    group: '/images/defaults/group.svg',
    groupCover: '/images/defaults/group-cover.svg',
    eventCover: '/images/defaults/event-cover.svg',
};

/** A background-image style for a cover, falling back to the default picture. */
export function coverStyle(cover: string | null | undefined, fallback: string) {
    return { backgroundImage: `url(${cover || fallback})` };
}
