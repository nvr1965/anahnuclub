import { CommunitySidebar } from '@/components/community-sidebar';
import InputError from '@/components/input-error';
import { PostList } from '@/components/post-list';
import { SocialLogin } from '@/components/social-login';
import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type Post, type Sidebar } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Lock, User, UserPlus } from 'lucide-react';
import { FormEventHandler } from 'react';

/**
 * Front page for guests, laid out like the old anahnu.club: banner, quick login, members and the latest posts.
 * Wide, so it has the room of the signed-in feed rather than its single middle column.
 */
export default function Welcome({ posts, sidebar }: { posts: { data: Post[] }; sidebar: Sidebar }) {
    const t = useT();
    const form = useForm({ email: '', password: '', remember: true as boolean });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('login'), { onFinish: () => form.reset('password') });
    };

    const field = 'flex items-center gap-2 rounded-xl bg-slate-100 px-3 focus-within:ring-2 focus-within:ring-[#117892]/30 dark:bg-neutral-800';

    return (
        <ClubLayout wide>
            <Head title="anahnu.club" />

            {/*
             * The banner's teal whatever the site style, the same as the sign-in and sign-up pages:
             * what the club is about and the way to join on the start side, the sign-in card on the end side.
             */}
            <section className="flex flex-col gap-8 overflow-hidden rounded-3xl bg-gradient-to-br from-[#117892] to-[#0b5a6e] p-5 text-white shadow-sm sm:p-8 lg:flex-row lg:items-center lg:justify-between lg:gap-10 lg:p-10">
                <div className="max-w-xl">
                    <div className="flex items-center gap-3">
                        <img src="/images/logo-mark.png" alt="" className="size-12 rounded-xl bg-white" />
                        <span className="text-2xl font-extrabold tracking-wider">anahnu.club</span>
                    </div>
                    <h1 className="mt-6 text-3xl leading-tight font-bold sm:text-4xl">{t('A community without borders')}</h1>
                    <p className="mt-3 text-base text-white/90">{t('Write in your language, read in yours. We translate the rest.')}</p>
                    <ul className="mt-5 space-y-2.5 text-[0.9375rem]">
                        {[t('Automatic translation of posts and messages'), t('Interest groups and news'), t('Our people are already here')].map(
                            (point) => (
                                <li key={point} className="flex items-center gap-3">
                                    <span className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-white/15">
                                        <Check className="size-3.5" />
                                    </span>
                                    {point}
                                </li>
                            ),
                        )}
                    </ul>
                    <Link
                        href={route('register')}
                        className="bg-cta hover:bg-cta-dark mt-7 inline-flex items-center gap-2 rounded-full px-6 py-3 text-[0.9375rem] font-semibold text-white shadow-lg"
                    >
                        <UserPlus className="size-4" /> {t('Create an account for free')}
                    </Link>
                </div>

                <form
                    onSubmit={submit}
                    className="w-full shrink-0 space-y-3 rounded-2xl bg-white p-5 text-slate-900 shadow-xl sm:p-6 lg:w-[360px] dark:bg-neutral-900 dark:text-neutral-100"
                >
                    <h2 className="text-lg font-semibold">{t('Already with us? Log in')}</h2>
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
                    <InputError message={form.errors.email} />
                    <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                        <label className="-my-2 inline-flex cursor-pointer items-center gap-2 py-2 select-none">
                            <input
                                type="checkbox"
                                className="size-5"
                                checked={form.data.remember}
                                onChange={(e) => form.setData('remember', e.target.checked)}
                            />
                            {t('Remember me')}
                        </label>
                        <Link href={route('password.request')} className="text-[#117892] hover:underline dark:text-[#5fb8cc]">
                            {t('Forgot password?')}
                        </Link>
                    </div>
                    <button
                        disabled={form.processing}
                        className="w-full rounded-xl bg-[#117892] py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#0b5a6e] disabled:opacity-60"
                    >
                        {t('Log in')}
                    </button>
                    <SocialLogin />
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
