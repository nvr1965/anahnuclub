import { CommunitySidebar } from '@/components/community-sidebar';
import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { type Paginated, type Post, type Sidebar } from '@/types';
import { Head } from '@inertiajs/react';

export default function Feed({ posts, sidebar }: { posts: Paginated<Post>; sidebar: Sidebar }) {
    const t = useT();

    return (
        <AppLayout aside={<CommunitySidebar sidebar={sidebar} />}>
            <Head title={t('Feed')} />
            <div className="space-y-4">
                <PostComposer />
                <PostList posts={posts} />
            </div>
        </AppLayout>
    );
}
