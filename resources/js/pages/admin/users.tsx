import { ActionButton, AdminSearch, Badge, card, type Page, Pager, send } from '@/components/admin-ui';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface Row {
    id: number;
    name: string;
    username: string | null;
    email: string;
    avatar: string | null;
    blocked: boolean;
    is_admin: boolean;
    posts_count: number;
    created_at: string | null;
    last_seen_at: string | null;
}

export default function AdminUsers({ users, q, filter }: { users: Page<Row>; q: string; filter: string | null }) {
    const t = useT();
    const getInitials = useInitials();
    const { auth, locale } = usePage<SharedData>().props;
    const filters = [
        { key: null, label: t('All') },
        { key: 'blocked', label: t('Blocked users') },
        { key: 'admins', label: t('Administrators') },
    ];

    return (
        <AdminLayout title={t('Users')} actions={<AdminSearch value={q} routeName="admin.users.index" params={{ filter: filter ?? undefined }} />}>
            <div className="mb-3 flex gap-2">
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
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {users.data.length === 0 && <p className="text-muted-foreground p-6 text-center">{t('Nothing found.')}</p>}
                {users.data.map((user) => {
                    const self = user.id === auth.user.id;
                    return (
                        <div key={user.id} className="flex flex-wrap items-center gap-3 p-3">
                            <Avatar className="size-10">
                                <AvatarImage src={user.avatar ?? undefined} alt="" className="object-cover" />
                                <AvatarFallback className="bg-brand-soft text-brand">{getInitials(user.name)}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span dir="auto" className="font-medium">
                                        {user.name}
                                    </span>
                                    {user.is_admin && <Badge tone="brand">{t('Administrator')}</Badge>}
                                    {user.blocked && <Badge tone="red">{t('Blocked')}</Badge>}
                                </div>
                                <div className="text-muted-foreground truncate text-xs">
                                    {user.username && <>@{user.username} · </>}
                                    {user.email} · {t(':count posts', { count: user.posts_count })}
                                    {user.last_seen_at && <> · {relativeTime(user.last_seen_at, locale)}</>}
                                </div>
                            </div>
                            {!self && (
                                <div className="flex flex-wrap gap-1">
                                    {user.posts_count > 0 && (
                                        <Link href={route('admin.posts.index', { user: user.id })} className="text-brand hover:bg-brand-soft rounded-md px-2.5 py-1 text-xs font-medium">
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
                                            onClick={() => confirm(t('Block :name and delete all their posts and comments?', { name: user.name })) && send('post', route('admin.users.purge', user.id))}
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
