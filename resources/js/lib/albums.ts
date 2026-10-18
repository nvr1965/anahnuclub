type Translate = (key: string, replace?: Record<string, string | number>) => string;

/** Albums the site keeps by itself are named in the reader's language. */
export function albumName(album: { name: string; kind: string | null }, t: Translate): string {
    switch (album.kind) {
        case 'timeline':
            return t('Timeline photos');
        case 'avatar':
            return t('Profile pictures');
        case 'cover':
            return t('Cover photos');
        default:
            return album.name;
    }
}
