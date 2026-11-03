import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

/** The letter's link was opened in a browser signed in as another member: the address is confirmed anyway. */
export default function EmailVerified({ email }: { email: string }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;

    return (
        <AuthLayout
            title={t('Email confirmed')}
            description={t(':email is confirmed. In this browser you are signed in as :name.', { email, name: auth.user.name })}
        >
            <Head title={t('Email confirmed')} />

            <div className="space-y-6 text-center">
                <Button asChild>
                    <Link href={route('dashboard')}>{t('Continue as :name', { name: auth.user.name })}</Link>
                </Button>

                <TextLink href={route('logout')} method="post" className="mx-auto block text-sm">
                    {t('Log out to sign in with the confirmed address')}
                </TextLink>
            </div>
        </AuthLayout>
    );
}
