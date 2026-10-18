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
    counts: { messages: number; friend_requests: number } | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin?: boolean;
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
    group: { id: number; name: string; slug: string } | null;
    likes_count: number;
    liked: boolean;
    comments: Comment[];
    url: string;
    can_delete: boolean;
    editable_text: string | null;
}

export interface Person {
    id: number;
    name: string;
    avatar: string | null;
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
}

export interface Sidebar {
    members: { id: number; name: string; avatar: string | null }[];
    members_count: number;
    groups: { name: string; slug: string; posts_count: number }[];
    photos: { id: number; thumb: string; url: string }[];
}
