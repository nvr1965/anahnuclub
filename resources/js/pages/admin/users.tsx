import { ActionButton, AdminSearch, Badge, card, type Page, Pager, send } from '@/components/admin-ui';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { DefaultAvatarFallback } from '@/components/user-avatar';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    name: string;
    username: string | null;
    email: string;
    avatar: string | null;
    blocked: boolean;
    verified: boolean;
    is_admin: boolean;
    is_super: boolean;
    rules_accepted_at: string | null;
    posts_count: number;
    created_at: string | null;
    last_seen_at: string | null;
}

export default function AdminUsers({ users, q, filter, unverified }: { users: Page<Row>; q: string; filter: string | null; unverified: number }) {
    const t = useT();
    const { auth, locale } = usePage<SharedData>().props;
    const filters = [
        { key: null, label: t('All') },
        { key: 'unverified', label: `${t('Email not confirmed')} (${unverified})` },
        { key: 'blocked', label: t('Blocked users') },
        { key: 'admins', label: t('Administrators') },
    ];
    // Only sign-ups that never confirmed their email can be picked: they are confirmed or deleted together.
    const selectable = users.data.filter((u) => !u.verified && !u.is_admin && !u.is_super).map((u) => u.id);
    const [selected, setSelected] = useState<number[]>([]);
    const picked = selected.filter((id) => selectable.includes(id));
    const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const confirmEmail = (ids: number[]) => {
        send('post', route('admin.users.verify'), { ids });
        setSelected([]);
    };
    const remove = (ids: number[]) => {
        if (!confirm(t('Delete :count members who did not confirm their email? This cannot be undone.', { count: ids.length }))) return;
        send('delete', route('admin.users.destroy'), { ids });
        setSelected([]);
    };

    return (
        <AdminLayout title={t('Users')} actions={<AdminSearch value={q} routeName="admin.users.index" params={{ filter: filter ?? undefined }} />}>
            <div className="mb-3 flex flex-wrap gap-2">
                {filters.map((f) => (
                    <Link
                        key={f.label}
                        href={route('admin.users.index', { q: q || undefined, filter: f.key ?? undefined })}
                        className={`rounded-full px-3 py-1 text-sm ${filter === f.key ? 'bg-brand text-white' : 'bg-white dark:bg-neutral-900'}`}
                    >
                        {f.label}
                    </Link>
                ))}
            </div>
            {selectable.length > 0 && (
                <div className="mb-3 flex flex-wrap items-center gap-3 text-sm">
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            className="accent-brand size-4"
                            checked={picked.length === selectable.length}
                            onChange={() => setSelected(picked.length === selectable.length ? [] : selectable)}
                        />
                        {t('Select all on this page')}
                    </label>
                    {picked.length > 0 && (
                        <>
                            <button
                                type="button"
                                onClick={() => confirmEmail(picked)}
                                className="bg-brand rounded-full px-3 py-1 font-medium text-white"
                            >
                                {t('Confirm email (:count)', { count: picked.length })}
                            </button>
                            <button type="button" onClick={() => remove(picked)} className="rounded-full bg-red-600 px-3 py-1 font-medium text-white">
                                {t('Delete selected (:count)', { count: picked.length })}
                            </button>
                        </>
                    )}
                </div>
            )}
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {users.data.length === 0 && <p className="text-muted-foreground p-6 text-center">{t('Nothing found.')}</p>}
                {users.data.map((user) => {
                    const self = user.id === auth.user.id;
                    return (
                        <div key={user.id} className="flex flex-wrap items-center gap-3 p-3">
                            {selectable.length > 0 && (
                                <input
                                    type="checkbox"
                                    className="accent-brand size-4 disabled:invisible"
                                    disabled={!selectable.includes(user.id)}
                                    checked={selected.includes(user.id)}
                                    onChange={() => toggle(user.id)}
                                />
                            )}
                            <Link href={route('people.show', user.id)}>
                                <Avatar className="size-10">
                                    <AvatarImage src={user.avatar ?? undefined} alt="" className="object-cover" />
                                    <DefaultAvatarFallback />
                                </Avatar>
                            </Link>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={route('people.show', user.id)} dir="auto" className="font-medium hover:underline">
                                        {user.name}
                                    </Link>
                                    {user.is_super ? (
                                        <Badge tone="brand">{t('Main administrator')}</Badge>
                                    ) : (
                                        user.is_admin && <Badge tone="brand">{t('Administrator')}</Badge>
                                    )}
                                    {user.blocked && <Badge tone="red">{t('Blocked')}</Badge>}
                                    {!user.verified && <Badge>{t('Email not confirmed')}</Badge>}
                                </div>
                                <div className="text-muted-foreground truncate text-xs">
                                    {user.username && <>@{user.username} · </>}
                                    {user.email} · {t(':count posts', { count: user.posts_count })}
                                    {user.last_seen_at && <> · {relativeTime(user.last_seen_at, locale)}</>}
                                    {' · '}
                                    {user.rules_accepted_at
                                        ? t('Rules accepted :date', { date: new Date(user.rules_accepted_at).toLocaleDateString(locale) })
                                        : t('Rules not accepted yet')}
                                </div>
                            </div>
                            {!self && !user.is_super && (
                                <div className="flex flex-wrap gap-1">
                                    {!user.verified && <ActionButton onClick={() => confirmEmail([user.id])}>{t('Confirm email')}</ActionButton>}
                                    {user.posts_count > 0 && (
                                        <Link
                                            href={route('admin.posts.index', { user: user.id })}
                                            className="text-brand hover:bg-brand-soft rounded-md px-2.5 py-1 text-xs font-medium"
                                        >
                                            {t('Posts')}
                                        </Link>
                                    )}
                                    <ActionButton onClick={() => send('patch', route('admin.users.update', user.id), { blocked: !user.blocked })}>
                                        {user.blocked ? t('Unblock') : t('Block')}
                                    </ActionButton>
                                    <ActionButton onClick={() => send('patch', route('admin.users.update', user.id), { is_admin: !user.is_admin })}>
                                        {user.is_admin ? t('Remove admin') : t('Make admin')}
                                    </ActionButton>
                                    {!user.is_admin && (
                                        <ActionButton
                                            danger
                                            onClick={() =>
                                                confirm(t('Block :name and delete all their posts and comments?', { name: user.name })) &&
                                                send('post', route('admin.users.purge', user.id))
                                            }
                                        >
                                            {t('Delete as spam')}
                                        </ActionButton>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
            <Pager page={users} />
        </AdminLayout>
    );
}
