import { LanguageSwitcher } from '@/components/language-switcher';
import { useT } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    const t = useT();

    return (
        <>
            <Head title="AnahnuClub" />
            <div className="flex min-h-screen flex-col items-center justify-center gap-8 bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <LanguageSwitcher className="absolute top-4 end-4" />
                <div className="max-w-md space-y-3 text-center">
                    <h1 className="text-3xl font-semibold">AnahnuClub</h1>
                    <p className="text-muted-foreground">{t('A community without borders. Write in your language, read in yours.')}</p>
                </div>
                <div className="flex gap-3">
                    <Link href={route('login')} className="rounded-md bg-neutral-900 px-5 py-2 text-sm text-white dark:bg-white dark:text-neutral-900">
                        {t('Log in')}
                    </Link>
                    <Link href={route('register')} className="rounded-md border px-5 py-2 text-sm">
                        {t('Register')}
                    </Link>
                </div>
            </div>
        </>
    );
}
