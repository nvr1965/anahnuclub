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
}

export default function AdminGroups({ groups, q }: { groups: Page<Row>; q: string }) {
    const t = useT();

    return (
        <AdminLayout title={t('Groups')} actions={<AdminSearch value={q} routeName="admin.groups.index" />}>
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
