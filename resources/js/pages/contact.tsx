import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { type FormEventHandler, type ReactNode } from 'react';

export default function Contact({ sent }: { sent: boolean }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const form = useForm({ name: '', email: '', subject: '', body: '', website: '' });
    const field =
        'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('contact.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const row = (label: string, error: string | undefined, input: ReactNode) => (
        <label className="block space-y-1">
            <span className="text-sm font-medium">{label}</span>
            {input}
            {error && <span className="block text-sm text-red-600">{error}</span>}
        </label>
    );

    return (
        <ClubLayout>
            <Head title={t('Contact us')} />
            <section className="mx-auto w-full max-w-[640px] rounded-lg bg-white p-6 shadow-sm sm:p-8 dark:bg-neutral-900">
                <h1 className="text-2xl font-bold">{t('Contact us')}</h1>
                <p className="text-muted-foreground mt-1 mb-5 text-[15px]">
                    {t('Questions, ideas, a complaint about a post or a request about your data: write to the club administration.')}
                </p>
                {sent ? (
                    <div className="flex flex-col items-center gap-3 py-8 text-center">
                        <CheckCircle2 className="size-12 text-green-600" />
                        <p className="text-lg font-semibold">{t('Thank you! Your message has been sent.')}</p>
                        <Link href={route('home')} className="text-brand font-medium hover:underline">
                            {t('Back to the club')}
                        </Link>
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-3">
                        {!auth.user && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {row(
                                    t('Your name'),
                                    form.errors.name,
                                    <input
                                        dir="auto"
                                        className={field}
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                    />,
                                )}
                                {row(
                                    t('Email'),
                                    form.errors.email,
                                    <input
                                        type="email"
                                        className={field}
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                    />,
                                )}
                            </div>
                        )}
                        {row(
                            t('Subject'),
                            form.errors.subject,
                            <input
                                dir="auto"
                                className={field}
                                value={form.data.subject}
                                onChange={(e) => form.setData('subject', e.target.value)}
                            />,
                        )}
                        {row(
                            t('Your message'),
                            form.errors.body,
                            <textarea
                                dir="auto"
                                rows={7}
                                className={field}
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                            />,
                        )}
                        {/* Hidden from people; bots that fill it in are ignored. */}
                        <input
                            type="text"
                            name="website"
                            tabIndex={-1}
                            autoComplete="off"
                            aria-hidden="true"
                            className="hidden"
                            value={form.data.website}
                            onChange={(e) => form.setData('website', e.target.value)}
                        />
                        <div className="flex justify-end">
                            <button
                                className="bg-brand hover:bg-brand-dark rounded-md px-6 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                disabled={form.processing || form.data.body.trim().length < 5}
                            >
                                {t('Send')}
                            </button>
                        </div>
                    </form>
                )}
            </section>
        </ClubLayout>
    );
}
