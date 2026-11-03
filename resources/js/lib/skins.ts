import { type SharedData, type Skin } from '@/types';
import { router, usePage } from '@inertiajs/react';

/** Names and one-line descriptions, as interface text keys. The styles are the site's own, only in the spirit of familiar networks. */
export const skinInfo: Record<Skin, { name: string; about: string }> = {
    club: { name: 'Club', about: 'Cards on grey, three columns, the menu on the side' },
    community: { name: 'Community', about: 'Denser and squarer, more posts on a screen' },
    large: { name: 'Large text', about: 'Bigger letters and buttons, stronger contrast' },
    stream: { name: 'Stream', about: 'One column of posts split by thin lines, menu at the bottom on phones' },
    showcase: { name: 'Showcase', about: 'Photos first and edge to edge, a photo grid in profiles' },
};

/** The style shown now, and a way to switch: the page changes at once, the server remembers it (for members on every device). */
export function useSkin() {
    const { skin, skins } = usePage<SharedData>().props;

    const setSkin = (next: Skin) => {
        document.documentElement.dataset.skin = next;
        router.post(route('skin.update'), { skin: next }, { preserveScroll: true, preserveState: true });
    };

    return { skin, skins, setSkin };
}
