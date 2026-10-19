import HeadingSmall from '@/components/heading-small';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { type ReactNode } from 'react';

interface Props {
    people: Person[];
    messenger: Person[];
    messages_from: 'everyone' | 'friends';
    groups: { id: number; name: string; slug: string }[];
}

export default function Blocked({ people, messenger, messages_from, groups }: Props) {
    const t = useT();
    const unblock = (url: string) => router.delete(url, { preserveScroll: true });

    const row = (key: string | number, link: ReactNode, url: string) => (
        <li key={key} className="flex items-center justify-between gap-3 py-2">
            {link}
            <button
                type="button"
                onClick={() => unblock(url)}
                className="shrink-0 rounded-md bg-slate-200 px-3 py-1.5 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700"
            >
                {t('Unblock')}
            </button>
        </li>
    );

    return (
        <AppLayout breadcrumbs={[{ title: t('Blocking'), href: '/settings/blocked' }]}>
            <Head title={t('Blocking')} />
            <SettingsLayout>
                <div className="space-y-4">
                    <HeadingSmall
                        title={t('Who can write to me')}
                        description={t('Chats from people who are not your friends wait under “Requests” in the messenger.')}
                    />
                    {(
                        [
                            ['everyone', t('Everyone (non-friends go to requests)')],
                            ['friends', t('Friends only')],
                        ] as const
                    ).map(([value, label]) => (
                        <label key={value} className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="messages_from"
                                className="accent-brand size-4"
                                checked={messages_from === value}
                                onChange={() => router.put(route('settings.messages'), { messages_from: value }, { preserveScroll: true })}
                            />
                            {label}
                        </label>
                    ))}
                </div>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Blocked people')}
                        description={t('They do not see you and you do not see them: no posts, comments, messages or friend requests.')}
                    />
                    {people.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('You have not blocked anyone.')}</p>
                    ) : (
                        <ul className="divide-y divide-slate-200 dark:divide-neutral-800">
                            {people.map((person) =>
                                row(
                                    person.id,
                                    <Link href={route('people.show', person.id)} className="flex min-w-0 items-center gap-3">
                                        <UserAvatar person={person} className="size-9" />
                                        <span dir="auto" className="truncate font-medium">
                                            {person.name}
                                        </span>
                                    </Link>,
                                    route('blocks.user.destroy', person.id),
                                ),
                            )}
                        </ul>
                    )}
                </div>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Blocked in the messenger')}
                        description={t('They cannot write to you; everything else stays as it is.')}
                    />
                    {messenger.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('Nobody is blocked in the messenger.')}</p>
                    ) : (
                        <ul className="divide-y divide-slate-200 dark:divide-neutral-800">
                            {messenger.map((person) =>
                                row(
                                    person.id,
                                    <Link href={route('people.show', person.id)} className="flex min-w-0 items-center gap-3">
                                        <UserAvatar person={person} className="size-9" />
                                        <span dir="auto" className="truncate font-medium">
                                            {person.name}
                                        </span>
                                    </Link>,
                                    route('blocks.messages.destroy', person.id),
                                ),
                            )}
                        </ul>
                    )}
                </div>
                <div className="space-y-6">
                    <HeadingSmall title={t('Blocked groups')} description={t('Posts and events of these groups are hidden from you.')} />
                    {groups.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('You have not blocked any group.')}</p>
                    ) : (
                        <ul className="divide-y divide-slate-200 dark:divide-neutral-800">
                            {groups.map((group) =>
                                row(
                                    group.id,
                                    <Link href={route('groups.show', group.slug)} className="flex min-w-0 items-center gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-200 dark:bg-neutral-800">
                                            <Users className="size-4" />
                                        </span>
                                        <span dir="auto" className="truncate font-medium">
                                            {group.name}
                                        </span>
                                    </Link>,
                                    route('blocks.group.destroy', group.slug),
                                ),
                            )}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
