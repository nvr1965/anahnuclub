import { GroupForm } from '@/components/group-form';
import { GroupTeam, OfferBanner, RoleBadge, type GroupRole, type OfferRole, type TeamMember } from '@/components/group-team';
import { InviteButton } from '@/components/invite-button';
import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type Paginated, type Person, type Post, type Sidebar } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Ban, Users } from 'lucide-react';

interface Group {
    id: number;
    name: string;
    slug: string;
    description: string;
    cover: string | null;
    avatar: string | null;
    is_private: boolean;
    description_text: string;
    members_count: number;
    is_member: boolean;
    blocked: boolean;
    role: GroupRole | null;
    offer: { id: number; role: OfferRole; from: Person } | null;
}

const secondary =
    'inline-flex items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700';

export default function GroupShow({
    group,
    posts,
    sidebar,
    team,
}: {
    group: Group;
    posts: Paginated<Post>;
    sidebar: Sidebar;
    team: TeamMember[] | null;
}) {
    const t = useT();

    return (
        <AppLayout sidebar={sidebar}>
            <Head title={group.name} />
            <div className="space-y-4">
                <section className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
                    <div
                        className="from-brand to-header-to h-48 bg-gradient-to-br bg-cover bg-center"
                        style={group.cover ? { backgroundImage: `url(${group.cover})` } : undefined}
                    />
                    <div className="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div className="min-w-0">
                            <h1 dir="auto" className="text-2xl font-semibold">
                                {group.name}
                            </h1>
                            <p className="text-muted-foreground mt-1 inline-flex items-center gap-1.5 text-sm">
                                <Users className="size-4" /> {t(':count members', { count: group.members_count })}
                                {group.role && <RoleBadge role={group.role} />}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {group.blocked ? (
                                <button type="button" className={secondary} onClick={() => router.delete(route('blocks.group.destroy', group.slug))}>
                                    <Ban className="size-4" /> {t('Unblock')}
                                </button>
                            ) : (
                                <>
                                    <InviteButton group={group} />
                                    {team && group.role && <GroupTeam group={group} team={team} myRole={group.role} />}
                                    {(group.role === 'owner' || group.role === 'admin') && (
                                        <GroupForm group={group} isOwner={group.role === 'owner'} />
                                    )}
                                    {!group.is_member && (
                                        <button
                                            onClick={() => router.post(route('groups.join', group.slug))}
                                            className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white"
                                        >
                                            {t('Join')}
                                        </button>
                                    )}
                                    {group.role !== 'owner' && (
                                        <button
                                            type="button"
                                            className={secondary}
                                            title={t('Block group')}
                                            aria-label={t('Block group')}
                                            onClick={() =>
                                                window.confirm(t('Block this group? You will leave it and stop seeing its posts.')) &&
                                                router.post(route('blocks.group', group.slug))
                                            }
                                        >
                                            <Ban className="size-4" />
                                        </button>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                    {group.description && (
                        <div
                            dir="auto"
                            className="prose prose-sm prose-slate dark:prose-invert max-w-none px-5 pb-5"
                            dangerouslySetInnerHTML={{ __html: group.description }}
                        />
                    )}
                </section>
                {group.offer && <OfferBanner group={group} offer={group.offer} />}
                {group.blocked ? (
                    <section className="text-muted-foreground rounded-lg bg-white p-4 text-center text-[15px] shadow-sm dark:bg-neutral-900">
                        {t('You blocked this group. Its posts are hidden from you.')}
                    </section>
                ) : (
                    <>
                        {group.is_member && <PostComposer groupId={group.id} />}
                        <PostList posts={posts} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
