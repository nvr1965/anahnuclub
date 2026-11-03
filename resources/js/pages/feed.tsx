import { MomentTray } from '@/components/moments/moment-tray';
import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type Paginated, type Post, type Sidebar, type MomentTray as Tray } from '@/types';
import { Head } from '@inertiajs/react';

export default function Feed({ posts, sidebar, moments }: { posts: Paginated<Post>; sidebar: Sidebar; moments?: Tray }) {
    const t = useT();

    return (
        <AppLayout sidebar={sidebar}>
            <Head title={t('Feed')} />
            <div className="space-y-4">
                {moments && <MomentTray tray={moments} />}
                <PostComposer />
                <PostList posts={posts} />
            </div>
        </AppLayout>
    );
}
