import ClubLayout from '@/layouts/club-layout';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface Props {
    page: { slug: string; title: string; body: string; locale: string; updated_at: string | null };
}

export default function PageShow({ page }: Props) {
    const t = useT();
    const { locale, languages } = usePage<SharedData>().props;
    const rtl = languages.find((l) => l.code === page.locale)?.rtl;

    return (
        <ClubLayout>
            <Head title={page.title} />
            <article
                dir={rtl ? 'rtl' : 'ltr'}
                lang={page.locale}
                className="mx-auto w-full max-w-[800px] rounded-lg bg-white p-6 shadow-sm sm:p-10 dark:bg-neutral-900"
            >
                <h1 className="mb-2 text-3xl font-bold">{page.title}</h1>
                {page.updated_at && (
                    <p className="text-muted-foreground mb-6 text-sm">
                        {t('Last updated')}:{' '}
                        {new Date(page.updated_at).toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' })}
                    </p>
                )}
                <div
                    className="prose prose-slate dark:prose-invert prose-h2:text-xl prose-a:text-brand max-w-none"
                    dangerouslySetInnerHTML={{ __html: page.body }}
                />
            </article>
        </ClubLayout>
    );
}
