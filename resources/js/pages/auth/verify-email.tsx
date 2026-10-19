// Components
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const t = useT();
    const { post, processing } = useForm({});
    const { auth } = usePage<SharedData>().props;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <AuthLayout
            title={t('Confirm your email')}
            description={t('We sent a letter to :email. Open the link in it to finish signing up.', { email: auth.user.email })}
        >
            <Head title={t('Email verification')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('A new verification link has been sent to the email address you provided during registration.')}
                </div>
            )}

            <form onSubmit={submit} className="space-y-6 text-center">
                <Button disabled={processing} variant="secondary">
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {t('Resend verification email')}
                </Button>

                <p className="text-muted-foreground text-sm">
                    {t('No letter? Check the spam folder, or')}{' '}
                    <Link href={route('profile.edit')} className="text-brand hover:underline">
                        {t('change the email address')}
                    </Link>
                </p>

                <TextLink href={route('logout')} method="post" className="mx-auto block text-sm">
                    {t('Log out')}
                </TextLink>
            </form>
        </AuthLayout>
    );
}
