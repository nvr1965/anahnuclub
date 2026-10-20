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

export interface SharedData {
    name: string;
    locale: string;
    dir: 'ltr' | 'rtl';
    languages: { code: string; native_name: string; rtl: boolean }[];
    translations: Record<string, string>;
    auth: Auth;
    counts: { messages: number; friend_requests: number; notifications: number } | null;
    footer: { pages: { slug: string; title: string }[]; donate: { label: string; url: string }[] };
    /** Sign-in services set up on the server, for guests. */
    social: string[];
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
    likes_count: number;
    liked: boolean;
    my_reaction: Reaction | null;
    reactions: Reaction[];
    comments: Comment[];
    url: string;
    can_delete: boolean;
    audience: Audience;
    audience_ids: number[];
    editable_text: string | null;
    photos: PhotoItem[];
    video: VideoItem | null;
    event: EventItem | null;
    link: LinkCard | null;
}

export type Reaction = 'like' | 'love' | 'care' | 'haha' | 'wow' | 'sad' | 'angry';

export interface LinkCard {
    url: string;
    domain: string;
    title: string | null;
    description: string | null;
    image: string | null;
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
    created_at: string | null;
    author?: Person | null;
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
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
}

export interface Sidebar {
    members: Person[];
    members_count: number;
    groups: { name: string; slug: string; posts_count: number }[];
    photos: { id: number; thumb: string; url: string }[];
}
