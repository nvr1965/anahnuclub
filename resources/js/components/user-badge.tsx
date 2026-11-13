import { useT } from '@/lib/i18n';
import {
    BadgeCheck,
    Crown,
    Feather,
    FlaskConical,
    Gem,
    Handshake,
    Heart,
    Languages,
    Megaphone,
    Newspaper,
    PenLine,
    Shield,
    User,
    Users,
} from 'lucide-react';
import { type ReactNode } from 'react';

/**
 * Badges beside a member's name. Levels are earned by posting (from "Site user" up to "Top author");
 * special badges are given by an administrator. The server sends the slug, the page names it.
 */
export const BADGES: Record<string, { label: string; icon: ReactNode; color: string; hint: string }> = {
    member: {
        label: 'Site user',
        icon: <User />,
        color: 'bg-slate-100 text-slate-700 dark:bg-neutral-800 dark:text-neutral-300',
        hint: 'Registered in the club',
    },
    'club-member': {
        label: 'Club member',
        icon: <Users />,
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        hint: 'Takes part in the club: posts and comments',
    },
    'co-author': {
        label: 'Co-author',
        icon: <PenLine />,
        color: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        hint: 'Posts regularly and the club reacts',
    },
    author: {
        label: 'Author',
        icon: <Feather />,
        color: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
        hint: 'One of the club’s active authors',
    },
    'top-author': {
        label: 'Top author',
        icon: <Crown />,
        color: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        hint: 'One of the most read authors of the club',
    },
    'club-author': {
        label: 'Club author',
        icon: <Newspaper />,
        color: 'bg-brand-soft text-brand',
        hint: 'Writes for the club: news and selected posts',
    },
    founder: {
        label: 'Founder',
        icon: <Gem />,
        color: 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
        hint: 'Was with the club from the start',
    },
    moderator: {
        label: 'Moderator',
        icon: <Shield />,
        color: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
        hint: 'Keeps order in the club',
    },
    'inner-circle': {
        label: 'Inner circle',
        icon: <Heart />,
        color: 'bg-pink-50 text-pink-700 dark:bg-pink-950 dark:text-pink-300',
        hint: 'A friend of the club, known to its founder in person',
    },
    ambassador: {
        label: 'Ambassador',
        icon: <Megaphone />,
        color: 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-300',
        hint: 'Brings new people to the club',
    },
    translator: {
        label: 'Translator',
        icon: <Languages />,
        color: 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300',
        hint: 'Helps translate the club',
    },
    'beta-tester': {
        label: 'Beta tester',
        icon: <FlaskConical />,
        color: 'bg-orange-50 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
        hint: 'Helped test the new club',
    },
    verified: {
        label: 'Verified',
        icon: <BadgeCheck />,
        color: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        hint: 'The club checked who this is',
    },
    partner: {
        label: 'Partner',
        icon: <Handshake />,
        color: 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
        hint: 'A partner of the club',
    },
};

/** The name of a badge in the reader's language. */
export function useBadgeName() {
    const t = useT();
    return (slug: string | null | undefined) => (slug && BADGES[slug] ? t(BADGES[slug].label) : '');
}

/** A small chip with the badge's picture and name; nothing for an unknown or missing one. */
export function UserBadge({ badge, size = 'sm', className = '' }: { badge?: string | null; size?: 'xs' | 'sm' | 'md'; className?: string }) {
    const t = useT();
    const b = badge ? BADGES[badge] : undefined;
    if (!b) return null;
    const sizes = {
        xs: 'gap-0.5 px-1.5 py-px text-[0.6875rem] [&>svg]:size-3',
        sm: 'gap-1 px-2 py-0.5 text-xs [&>svg]:size-3.5',
        md: 'gap-1.5 px-2.5 py-1 text-sm [&>svg]:size-4',
    };

    return (
        <span
            title={t(b.hint)}
            className={`inline-flex shrink-0 items-center rounded-full align-middle leading-none font-medium whitespace-nowrap ${sizes[size]} ${b.color} ${className}`}
        >
            {b.icon}
            {t(b.label)}
        </span>
    );
}
