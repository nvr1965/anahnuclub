import { PostList } from '@/components/post-list';
import AppLayout from '@/layouts/app-layout';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type Post, type SharedData, type Sidebar } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

/** One post on its own page: what a shared link opens. */
export default function PostShow({ post, sidebar }: { post: { data: Post } | Post; sidebar: Sidebar }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const item = 'data' in post ? post.data : post;
    const title = item.title ?? item.author?.name ?? 'anahnu.club';
    const content = (
        <div className="space-y-4">
            <Head title={title} />
            <PostList posts={{ data: [item] }} full />
            {!auth.user && (
                <div className="rounded-lg bg-white p-5 text-center shadow-sm dark:bg-neutral-900">
                    <p className="mb-3 text-[15px]">{t('Join anahnu.club to comment and meet the community.')}</p>
                    <Link href={route('register')} className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white">
                        {t('Sign up')}
                    </Link>
                </div>
            )}
        </div>
    );

    return auth.user ? (
        <AppLayout sidebar={sidebar}>{content}</AppLayout>
    ) : (
        <ClubLayout>
            <div className="mx-auto w-full max-w-[680px]">{content}</div>
        </ClubLayout>
    );
}
