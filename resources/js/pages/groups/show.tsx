import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { type Paginated, type Post, type Sidebar } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';

interface Group {
    id: number;
    name: string;
    slug: string;
    description: string;
    cover: string | null;
    members_count: number;
    is_member: boolean;
}

export default function GroupShow({ group, posts, sidebar }: { group: Group; posts: Paginated<Post>; sidebar: Sidebar }) {
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
                            </p>
                        </div>
                        {!group.is_member && (
                            <button
                                onClick={() => router.post(route('groups.join', group.slug))}
                                className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white"
                            >
                                {t('Join')}
                            </button>
                        )}
                    </div>
                    {group.description && (
                        <div
                            dir="auto"
                            className="prose prose-sm prose-slate dark:prose-invert max-w-none px-5 pb-5"
                            dangerouslySetInnerHTML={{ __html: group.description }}
                        />
                    )}
                </section>
                {group.is_member && <PostComposer groupId={group.id} />}
                <PostList posts={posts} />
            </div>
        </AppLayout>
    );
}
