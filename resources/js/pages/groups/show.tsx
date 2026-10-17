import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { type Paginated, type Post } from '@/types';
import { Head, router } from '@inertiajs/react';

interface Group {
    id: number;
    name: string;
    slug: string;
    description: string;
    cover: string | null;
    members_count: number;
    is_member: boolean;
}

export default function GroupShow({ group, posts }: { group: Group; posts: Paginated<Post> }) {
    const t = useT();

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Groups'), href: route('groups.index') },
                { title: group.name, href: route('groups.show', group.slug) },
            ]}
        >
            <Head title={group.name} />
            <div className="mx-auto w-full max-w-2xl space-y-4 p-4">
                {group.cover && <img src={group.cover} alt="" className="h-40 w-full rounded-xl object-cover" />}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{group.name}</h1>
                        <p className="text-muted-foreground text-sm">{t(':count members', { count: group.members_count })}</p>
                    </div>
                    {!group.is_member && <Button onClick={() => router.post(route('groups.join', group.slug))}>{t('Join')}</Button>}
                </div>
                {group.description && (
                    <div className="prose prose-sm dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: group.description }} />
                )}
                {group.is_member && <PostComposer groupId={group.id} />}
                <PostList posts={posts} />
            </div>
        </AppLayout>
    );
}
