import { CommunitySidebar } from '@/components/community-sidebar';
import InputError from '@/components/input-error';
import { PostList } from '@/components/post-list';
import { SocialLogin } from '@/components/social-login';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type Post, type Sidebar } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Lock, User, UserPlus } from 'lucide-react';
import { FormEventHandler } from 'react';

/** Front page for guests, laid out like the old anahnu.club: banner, quick login, members and the latest posts. */
export default function Welcome({ posts, sidebar }: { posts: { data: Post[] }; sidebar: Sidebar }) {
    const t = useT();
    const form = useForm({ email: '', password: '', remember: false });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('login'), { onFinish: () => form.reset('password') });
    };

    const field = 'flex items-center gap-2 rounded-xl bg-slate-100 px-3 focus-within:ring-2 focus-within:ring-brand/30 dark:bg-neutral-800';

    return (
        <ClubLayout>
            <Head title="anahnu.club" />

            <section className="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200/70 dark:bg-neutral-900 dark:ring-neutral-800">
                <div className="bg-brand relative">
                    <img
                        src="/images/logo-banner.png"
                        alt="anahnu.club"
                        className="mx-auto h-32 w-auto object-contain sm:box-content sm:h-44 sm:pb-24"
                    />
                    <div className="from-ink/85 via-ink/50 flex flex-col gap-4 bg-gradient-to-t to-transparent px-6 pt-10 pb-6 text-white sm:absolute sm:inset-x-0 sm:bottom-0 sm:flex-row sm:items-end sm:justify-between">
                        <div className="max-w-xl">
                            <h1 className="text-2xl font-semibold">{t('Join us!')}</h1>
                            <p className="mt-1 text-sm text-white/85">{t('A community without borders. Write in your language, read in yours.')}</p>
                        </div>
                        <Link
                            href={route('register')}
                            className="bg-cta hover:bg-cta-dark inline-flex shrink-0 items-center gap-2 self-start rounded-full px-5 py-2.5 text-sm font-medium text-white shadow-lg sm:self-auto"
                        >
                            <UserPlus className="size-4" /> {t('Create an account')}
                        </Link>
                    </div>
                </div>

                <form onSubmit={submit} className="grid gap-3 p-5 sm:grid-cols-[1fr_1fr_auto]">
                    <label className={field}>
                        <User className="text-muted-foreground size-4" />
                        <input
                            className="w-full bg-transparent py-2.5 text-sm outline-none"
                            placeholder={t('Email or username')}
                            autoComplete="username"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </label>
                    <label className={field}>
                        <Lock className="text-muted-foreground size-4" />
                        <input
                            type="password"
                            className="w-full bg-transparent py-2.5 text-sm outline-none"
                            placeholder={t('Password')}
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </label>
                    <button
                        disabled={form.processing}
                        className="from-header-from to-header-to rounded-xl bg-gradient-to-r px-8 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-95 disabled:opacity-60"
                    >
                        {t('Log in')}
                    </button>
                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-5 gap-y-1 text-sm sm:col-span-3">
                        <label className="inline-flex items-center gap-2">
                            <input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />
                            {t('Remember me')}
                        </label>
                        <Link href={route('password.request')} className="text-brand hover:underline">
                            {t('Forgot password?')}
                        </Link>
                        <InputError message={form.errors.email} />
                    </div>
                    <SocialLogin className="sm:col-span-3" />
                </form>
            </section>

            <div className="mt-6 grid gap-6 lg:grid-cols-[300px_1fr]">
                <aside className="order-2 space-y-6 lg:order-1">
                    <CommunitySidebar sidebar={sidebar} />
                </aside>
                <div className="order-1 min-w-0 lg:order-2">
                    <PostList posts={posts} />
                </div>
            </div>
        </ClubLayout>
    );
}
