/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import { useT } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { PasswordInput } from '@/components/password-input';
import { SocialLogin } from '@/components/social-login';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    terms: boolean;
};

export default function Register() {
    const t = useT();
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title={t('Create an account')} description={t('Enter your details below to create your account')}>
            <Head title={t('Register')} />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                            placeholder={t('Full name')}
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={2}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={processing}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <PasswordInput
                            id="password"
                            required
                            tabIndex={3}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            disabled={processing}
                            placeholder={t('Password')}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">{t('Confirm password')}</Label>
                        <PasswordInput
                            id="password_confirmation"
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            disabled={processing}
                            placeholder={t('Confirm password')}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="grid gap-1">
                        <label className="flex items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                name="terms"
                                required
                                tabIndex={5}
                                className="accent-brand mt-0.5 size-4 shrink-0"
                                checked={data.terms}
                                onChange={(e) => setData('terms', e.target.checked)}
                                disabled={processing}
                            />
                            <span>
                                {t('I am 16 or older and accept the')}{' '}
                                <a href="/p/terms" target="_blank" rel="noreferrer" className="text-foreground underline underline-offset-4">
                                    {t('terms of use')}
                                </a>{' '}
                                {t('and the')}{' '}
                                <a href="/p/rules" target="_blank" rel="noreferrer" className="text-foreground underline underline-offset-4">
                                    {t('community rules')}
                                </a>
                            </span>
                        </label>
                        <InputError message={errors.terms} />
                    </div>

                    <Button type="submit" className="mt-2 w-full" tabIndex={6} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Create account')}
                    </Button>
                    <SocialLogin />
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    {t('Already have an account?')}{' '}
                    <TextLink href={route('login')} tabIndex={7}>
                        {t('Log in')}
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
