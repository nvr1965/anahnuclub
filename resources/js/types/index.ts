import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export type Skin = 'club' | 'community' | 'large' | 'stream' | 'showcase' | 'forum';

export interface SharedData {
    name: string;
    locale: string;
    dir: 'ltr' | 'rtl';
    languages: { code: string; native_name: string; rtl: boolean }[];
    translations: Record<string, string> | null;
    translations_version: string;
    auth: Auth;
    counts: { messages: number; friend_requests: number; notifications: number } | null;
    footer: { pages: { slug: string; title: string }[]; donate: { label: string; url: string }[] };
    /** Sign-in services set up on the server, for guests. */
    social: string[];
    /** The interface style and the ones members may pick (App\\Support\\Skins). */
    skin: Skin;
    skins: Skin[];
    [key: string]: unknown;
}

/** Who sees a post, as on Facebook. */
export type Audience = 'public' | 'friends' | 'except' | 'specific' | 'only_me';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin?: boolean;
    default_audience?: Audience;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Author {
    id: number;
    name: string;
    avatar: string | null;
    /** The badge beside the name: a special one given by the club, or the level earned. */
    badge?: string | null;
}

export interface TranslationInfo {
    type: string;
    pending: boolean;
    fields: Record<string, string>;
}

export interface Translatable {
    id: number;
    translation: TranslationInfo | null;
}

export interface Comment extends Translatable {
    parent_id: number | null;
    body: string;
    created_at: string | null;
    author: Author | null;
}

export interface Post extends Translatable {
    type: string;
    title: string | null;
    body: string;
    created_at: string | null;
    author: Author | null;
    /** A post of the main administrator, shown as the club's, with comments closed. */
    official: boolean;
    group: { id: number; name: string; slug: string } | null;
    /** Put on a friend's profile: shown as "Author ▸ Friend". */
    wall: { id: number; name: string } | null;
    /** Friends tagged beside the author's name: "Anna — with Boris and Vera". */
    with: { id: number; name: string }[];
    /** The reader is tagged or named in the post and may take the tag off. */
    tagged_me: boolean;
    /** A share: the post it carries, null inside when that post was deleted or is closed to the reader. */
    shared: { post: SharedPost | null } | null;
    /** Whether the reader may share it inside the club: to their feed, a group or a friend's profile. */
    can_share: boolean;
    likes_count: number;
    liked: boolean;
    my_reaction: Reaction | null;
    reactions: Reaction[];
    comments: Comment[];
    url: string;
    can_delete: boolean;
    /** Pinned at the top of the page it is shown on (the feed, the author's profile, the group). */
    pinned: boolean;
    /** Where this reader may pin the post, and whether it is pinned there now. */
    pins: Partial<Record<PinPlace, boolean>> | null;
    audience: Audience;
    audience_ids: number[];
    editable_text: string | null;
    photos: PhotoItem[];
    video: VideoItem | null;
    event: EventItem | null;
    link: LinkCard | null;
    embed: EmbedCard | null;
    embeddable: boolean;
    embed_url: string;
}

/** The post inside a share, as Facebook shows it. */
export interface SharedPost extends Translatable {
    type: string;
    title: string | null;
    body: string;
    created_at: string | null;
    author: Author | null;
    official: boolean;
    group: { id: number; name: string; slug: string } | null;
    url: string;
    audience: Audience;
    photos: PhotoItem[];
    video: VideoItem | null;
    event: EventItem | null;
    link: LinkCard | null;
    embed: EmbedCard | null;
}

export type Reaction = 'like' | 'love' | 'care' | 'haha' | 'wow' | 'sad' | 'angry';

export interface LinkCard {
    url: string;
    domain: string;
    title: string | null;
    description: string | null;
    image: string | null;
}

/** A post or video of Facebook, Instagram, Telegram, X, YouTube, Vimeo or TikTok, played inside a post. */
export interface EmbedCard {
    provider: string;
    src: string;
    url: string;
    /** null: a 16:9 player. */
    height: number | null;
}

export interface PhotoItem {
    id: number;
    thumb: string;
    url: string;
    caption: string | null;
    likes_count?: number;
    liked?: boolean;
    post_url?: string | null;
    album_id?: number | null;
}

export interface VideoItem {
    id: number;
    title: string;
    provider: 'youtube' | 'vimeo' | 'file';
    embed_url: string | null;
    file_url: string | null;
    thumb: string | null;
    is_clip?: boolean;
    width?: number | null;
    height?: number | null;
    created_at: string | null;
    author?: Person | null;
}

/** The strip of moments above the feed. */
/** The newest moment of a person, on their card in the strip above the feed. */
export type PinPlace = 'feed' | 'profile' | 'group';

export interface MomentCover {
    kind: 'photo' | 'video' | 'text';
    thumb: string | null;
    text: string | null;
    background: string | null;
}

export interface MomentTray {
    people: { user: Person; count: number; unseen: boolean; cover: MomentCover }[];
    lists: { id: number; name: string }[];
    /** How many moments of the viewer's own are up now. */
    mine: number;
}

export interface MomentItem extends Translatable {
    kind: 'photo' | 'video' | 'text';
    file_url: string | null;
    thumb: string | null;
    text: string | null;
    text_position: 'top' | 'center' | 'bottom';
    background: string | null;
    duration: number | null;
    created_at: string | null;
    expires_at: string | null;
    seen?: boolean;
    views?: number | null;
    can_delete?: boolean;
}

/** A clip as the Clips screen shows it: the post it belongs to, with its vertical video. */
export interface ClipItem extends Translatable {
    url: string;
    body: string;
    author: Person | null;
    video: VideoItem;
    views: number;
    likes_count: number;
    my_reaction: string | null;
    comments_count: number;
    can_delete: boolean;
}

export interface EventItem {
    id: number;
    title: string;
    location: string | null;
    starts_at: string | null;
    ends_at: string | null;
    cover: string | null;
    going_count: number;
    my_status: 'going' | 'interested' | null;
}

export interface Person {
    id: number;
    name: string;
    avatar: string | null;
    /** Seen on the site in the last few minutes. */
    online?: boolean;
    /** The badge beside the name: a special one given by the club, or the level earned. */
    badge?: string | null;
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page?: number; total?: number };
}

export interface Sidebar {
    members: Person[];
    members_count: number;
    groups: { name: string; slug: string; posts_count: number }[];
    photos: { id: number; thumb: string; url: string }[];
}
