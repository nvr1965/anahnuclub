import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import { useT } from '@/lib/i18n';
import AppLayout from '@/layouts/app-layout';
import { type Paginated, type Post } from '@/types';
import { Head } from '@inertiajs/react';

export default function Feed({ posts }: { posts: Paginated<Post> }) {
    const t = useT();

    return (
        <AppLayout breadcrumbs={[{ title: t('Feed'), href: route('dashboard') }]}>
            <Head title={t('Feed')} />
            <div className="mx-auto w-full max-w-2xl space-y-4 p-4">
                <PostComposer />
                <PostList posts={posts} />
            </div>
        </AppLayout>
    );
}
