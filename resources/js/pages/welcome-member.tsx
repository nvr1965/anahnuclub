import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Languages, UserPen, Users, UsersRound } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    /** The member accepted earlier rules; only the changed ones are asked about. */
    again: boolean;
    rules: { title: string; body: string; locale: string } | null;
}

/** First entry: a greeting, where to start, and the community rules to accept with a tick. */
export default function WelcomeMember({ again, rules }: Props) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const form = useForm({ accept: false });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('welcome.accept'));
    };

    const steps = [
        { icon: UserPen, title: t('Fill in your profile'), text: t('A photo, your city and a few words about yourself help people recognise you.') },
        { icon: Users, title: t('Find friends'), text: t('Look through the members or invite your friends from WhatsApp, Telegram or Facebook.') },
        { icon: UsersRound, title: t('Join groups'), text: t('Groups bring together people with the same interests, city or trade.') },
        { icon: Languages, title: t('Write in your own language'), text: t('Posts and messages are translated for every reader automatically.') },
    ];

    return (
        <ClubLayout>
            <Head title={again ? t('The rules have changed') : t('Welcome to the club')} />
            <div className="mx-auto max-w-2xl space-y-4">
                <section className="rounded-lg bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h1 dir="auto" className="text-2xl font-bold">
                        {again ? t('The rules have changed') : t('Welcome to the club, :name!', { name: auth.user.name })}
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        {again
                            ? t('We have updated the community rules. Please read them and confirm that you accept them.')
                            : t('Anahnu.Club is a place for conversation, friendship and mutual support. Here is where to start.')}
                    </p>
                    {!again && (
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {steps.map((step) => (
                                <div key={step.title} className="flex gap-3 rounded-lg border border-slate-200 p-3 dark:border-neutral-800">
                                    <step.icon className="text-brand mt-0.5 size-5 shrink-0" />
                                    <span>
                                        <span className="block font-semibold">{step.title}</span>
                                        <span className="text-muted-foreground block text-sm">{step.text}</span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                {rules && (
                    <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm dark:bg-neutral-900">
                        <h2 className="text-xl font-bold">{rules.title}</h2>
                        <div
                            dir="auto"
                            lang={rules.locale}
                            className="prose prose-sm prose-slate dark:prose-invert max-h-96 max-w-none overflow-y-auto rounded-md border border-slate-200 p-4 dark:border-neutral-800"
                            dangerouslySetInnerHTML={{ __html: rules.body }}
                        />
                        <label className="flex items-start gap-3 text-[15px]">
                            <input
                                type="checkbox"
                                className="accent-brand mt-1 size-4 shrink-0"
                                checked={form.data.accept}
                                onChange={(e) => form.setData('accept', e.target.checked)}
                            />
                            <span>{t('I have read the community rules and accept them')}</span>
                        </label>
                        {form.errors.accept && <p className="text-sm text-red-600">{form.errors.accept}</p>}
                        <button
                            disabled={!form.data.accept || form.processing}
                            className="bg-brand hover:bg-brand-dark w-full rounded-md py-2.5 font-semibold text-white disabled:opacity-40"
                        >
                            {again ? t('Accept and continue') : t('Accept and enter the club')}
                        </button>
                    </form>
                )}
            </div>
        </ClubLayout>
    );
}
