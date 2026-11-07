import { ActionButton, AdminSearch, Badge, card, type Page, Pager, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

interface Row {
    id: number;
    name: string;
    slug: string;
    owner: string | null;
    published: boolean;
    is_private: boolean;
    members_count: number;
    posts_count: number;
    created_at: string | null;
}

type Sort = 'name' | 'newest' | 'oldest';

export default function AdminGroups({
    groups,
    q,
    stale,
    stale_count,
    sort,
}: {
    groups: Page<Row>;
    q: string;
    stale: boolean;
    stale_count: number;
    sort: Sort;
}) {
    const t = useT();
    const sorts: Record<Sort, string> = { name: t('By name'), newest: t('Newest first'), oldest: t('Oldest first') };
    const params = (extra: Record<string, unknown>) => ({ ...(stale ? { stale: 1 } : {}), ...(q ? { q } : {}), sort, ...extra });

    return (
        <AdminLayout
            title={t('Groups')}
            actions={<AdminSearch value={q} routeName="admin.groups.index" params={{ ...(stale ? { stale: 1 } : {}), sort }} />}
        >
            <div className="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <Link
                    href={route('admin.groups.index', stale ? {} : { stale: 1 })}
                    className={`rounded-full px-3 py-1 font-medium shadow-sm ${stale ? 'bg-brand text-white' : 'bg-white dark:bg-neutral-900'}`}
                >
                    {t('Empty for over a year')} ({stale_count})
                </Link>
                <span className="text-muted-foreground ms-2">{t('Sort:')}</span>
                {(Object.keys(sorts) as Sort[]).map((s) => (
                    <Link
                        key={s}
                        href={route('admin.groups.index', params({ sort: s }))}
                        preserveScroll
                        className={`rounded-full px-3 py-1 font-medium shadow-sm ${sort === s ? 'bg-brand text-white' : 'bg-white dark:bg-neutral-900'}`}
                    >
                        {sorts[s]}
                    </Link>
                ))}
                {stale && stale_count > 0 && (
                    <ActionButton
                        danger
                        onClick={() =>
                            confirm(t('Delete :count groups older than a year that have no posts? This cannot be undone.', { count: stale_count })) &&
                            send('post', route('admin.groups.prune-stale'))
                        }
                    >
                        {t('Delete all :count', { count: stale_count })}
                    </ActionButton>
                )}
            </div>
            {stale && (
                <p className="text-muted-foreground mb-3 text-sm">
                    {t('Groups created more than a year ago with no posts, albums, videos or events.')}
                </p>
            )}
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {groups.data.length === 0 && <p className="text-muted-foreground p-6 text-center">{t('Nothing found.')}</p>}
                {groups.data.map((group) => (
                    <div key={group.id} className="flex flex-wrap items-center gap-3 p-3">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <Link href={route('groups.show', group.slug)} dir="auto" className="font-medium hover:underline">
                                    {group.name}
                                </Link>
                                {!group.published && <Badge tone="red">{t('Hidden')}</Badge>}
                                {group.is_private && <Badge>{t('Private')}</Badge>}
                            </div>
                            <div className="text-muted-foreground text-xs">
                                {group.owner && <>{group.owner} · </>}
                                {group.created_at && <>{group.created_at} · </>}
                                {t(':count members', { count: group.members_count })} · {t(':count posts', { count: group.posts_count })}
                            </div>
                        </div>
                        <div className="flex gap-1">
                            <ActionButton onClick={() => send('patch', route('admin.groups.update', group.id), { published: !group.published })}>
                                {group.published ? t('Hide') : t('Show')}
                            </ActionButton>
                            <ActionButton
                                danger
                                onClick={() =>
                                    confirm(t('Delete the group “:name” with all its posts?', { name: group.name })) &&
                                    send('delete', route('admin.groups.destroy', group.id))
                                }
                            >
                                {t('Delete')}
                            </ActionButton>
                        </div>
                    </div>
                ))}
            </div>
            <Pager page={groups} />
        </AdminLayout>
    );
}
