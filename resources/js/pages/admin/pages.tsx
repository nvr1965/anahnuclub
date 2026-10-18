import { Badge, card } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { Eye, Languages, Plus, Trash2 } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface Language {
    code: string;
    native_name: string;
    rtl: boolean;
    enabled: boolean;
}

type Content = Record<string, { title: string; body: string; auto?: boolean }>;

interface Props {
    languages: Language[];
    pages: { slug: string; title: string; published: boolean; in_footer: boolean; languages: string[] }[];
    edit: { slug: string | null; content: Content; in_footer: boolean; published: boolean; sort: number } | null;
    donate_links: { label: string; url: string }[];
    can_translate: boolean;
}

const field =
    'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';
const primary = 'bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white disabled:opacity-40';
const secondary =
    'inline-flex items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 disabled:opacity-40 dark:bg-neutral-800 dark:hover:bg-neutral-700';

export default function AdminPages({ languages, pages, edit, donate_links, can_translate }: Props) {
    const t = useT();
    const settings = useForm<{ donate_links: { label: string; url: string }[] }>({ donate_links: donate_links });
    const links = settings.data.donate_links;
    const setLink = (i: number, key: 'label' | 'url', value: string) =>
        settings.setData(
            'donate_links',
            links.map((l, j) => (j === i ? { ...l, [key]: value } : l)),
        );

    return (
        <AdminLayout
            title={t('Pages and footer')}
            actions={
                <Link href={route('admin.pages.index', { page: 'new' })} className={`${primary} inline-flex items-center gap-2`}>
                    <Plus className="size-4" /> {t('New page')}
                </Link>
            }
        >
            <div className="space-y-4">
                <div className={`${card} divide-y dark:divide-neutral-800`}>
                    {pages.map((page) => (
                        <Link
                            key={page.slug}
                            href={route('admin.pages.index', { page: page.slug })}
                            preserveScroll
                            className={`flex flex-wrap items-center gap-2 p-3 hover:bg-slate-50 dark:hover:bg-neutral-800/50 ${edit?.slug === page.slug ? 'bg-brand-soft/50' : ''}`}
                        >
                            <span dir="auto" className="flex-1 font-medium">
                                {page.title}
                            </span>
                            {!page.published && <Badge tone="red">{t('Hidden')}</Badge>}
                            {page.in_footer && <Badge>{t('In footer')}</Badge>}
                            {languages.map((l) => (
                                <Badge key={l.code} tone={page.languages.includes(l.code) ? 'green' : 'gray'}>
                                    {l.code}
                                </Badge>
                            ))}
                        </Link>
                    ))}
                    {pages.length === 0 && <p className="text-muted-foreground p-4 text-sm">{t('No pages yet.')}</p>}
                </div>

                {edit && <Editor key={edit.slug ?? 'new'} edit={edit} languages={languages} canTranslate={can_translate} />}

                <form
                    className={`${card} space-y-2 p-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        settings.patch(route('admin.settings.update'), { preserveScroll: true });
                    }}
                >
                    <h2 className="font-semibold">{t('Donation button')}</h2>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Ways to donate under the “Support Anahnu Club” button in the footer. With several, the button opens a menu. Remove all to hide it.',
                        )}
                    </p>
                    {links.map((link, i) => (
                        <div key={i} className="flex flex-wrap gap-2">
                            <input
                                className={`${field.replace('w-full', '')} w-36`}
                                placeholder="Ko-fi"
                                value={link.label}
                                onChange={(e) => setLink(i, 'label', e.target.value)}
                            />
                            <input
                                type="url"
                                className={`${field.replace('w-full', '')} min-w-0 flex-1`}
                                placeholder="https://ko-fi.com/…"
                                value={link.url}
                                onChange={(e) => setLink(i, 'url', e.target.value)}
                            />
                            <button
                                type="button"
                                className={secondary}
                                aria-label={t('Delete')}
                                onClick={() =>
                                    settings.setData(
                                        'donate_links',
                                        links.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </button>
                            {(settings.errors as Record<string, string>)[`donate_links.${i}.url`] && (
                                <p className="w-full text-sm text-red-600">{(settings.errors as Record<string, string>)[`donate_links.${i}.url`]}</p>
                            )}
                        </div>
                    ))}
                    <div className="flex flex-wrap justify-between gap-2">
                        <button
                            type="button"
                            className={secondary}
                            disabled={links.length >= 10}
                            onClick={() => settings.setData('donate_links', [...links, { label: '', url: '' }])}
                        >
                            <Plus className="size-4" /> {t('Add a way to donate')}
                        </button>
                        <button
                            className={primary}
                            disabled={settings.processing || !settings.isDirty || links.some((l) => !l.label.trim() || !l.url.trim())}
                        >
                            {t('Save')}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

function Editor({ edit, languages, canTranslate }: { edit: NonNullable<Props['edit']>; languages: Language[]; canTranslate: boolean }) {
    const t = useT();
    const [tab, setTab] = useState(() => languages.find((l) => edit.content[l.code]?.title)?.code ?? languages[0]?.code);
    const [translating, setTranslating] = useState(false);
    const blank = Object.fromEntries(
        languages.map((l) => [l.code, { ...edit.content[l.code], title: edit.content[l.code]?.title ?? '', body: edit.content[l.code]?.body ?? '' }]),
    ) as Content;
    const form = useForm({ slug: edit.slug ?? '', content: blank, in_footer: edit.in_footer, published: edit.published, sort: edit.sort });
    const current = form.data.content[tab] ?? { title: '', body: '' };
    const rtl = languages.find((l) => l.code === tab)?.rtl;
    const missing = languages.filter((l) => !form.data.content[l.code]?.title.trim());

    const setText = (key: 'title' | 'body', value: string) =>
        form.setData('content', { ...form.data.content, [tab]: { ...current, [key]: value, auto: false } });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        if (edit.slug) form.put(route('admin.pages.update', edit.slug), { preserveScroll: true });
        else form.post(route('admin.pages.store'), { preserveScroll: true });
    };

    const translate = () => {
        if (!edit.slug) return;
        setTranslating(true);
        const run = () =>
            router.post(route('admin.pages.translate', edit.slug!), { from: tab }, { preserveScroll: true, onFinish: () => setTranslating(false) });
        if (form.isDirty)
            form.put(route('admin.pages.update', edit.slug), { preserveScroll: true, onSuccess: run, onError: () => setTranslating(false) });
        else run();
    };

    const remove = () => {
        if (edit.slug && window.confirm(t('Delete this page?'))) router.delete(route('admin.pages.destroy', edit.slug));
    };

    return (
        <form onSubmit={save} className={`${card} space-y-3 p-4`}>
            <div className="flex flex-wrap gap-1 border-b border-slate-200 dark:border-neutral-800">
                {languages.map((l) => (
                    <button
                        key={l.code}
                        type="button"
                        onClick={() => setTab(l.code)}
                        className={`-mb-px border-b-2 px-3 py-2 text-sm font-semibold ${tab === l.code ? 'border-brand text-brand' : 'text-muted-foreground border-transparent'}`}
                    >
                        {l.native_name}
                        {!form.data.content[l.code]?.title.trim() && <span className="ms-1 text-red-500">•</span>}
                        {form.data.content[l.code]?.auto && <Languages className="ms-1 inline size-3.5" />}
                    </button>
                ))}
            </div>
            {current.auto && <p className="text-muted-foreground text-sm">{t('This text was translated automatically. Check it and save.')}</p>}
            <input
                dir={rtl ? 'rtl' : 'ltr'}
                className={`${field} text-lg font-semibold`}
                placeholder={t('Title')}
                value={current.title}
                onChange={(e) => setText('title', e.target.value)}
            />
            <textarea
                dir={rtl ? 'rtl' : 'ltr'}
                rows={16}
                className={`${field} font-mono text-[13px]`}
                placeholder={t('Text of the page. HTML is allowed: <h2>, <p>, <a>, <ul>, <strong>…')}
                value={current.body}
                onChange={(e) => setText('body', e.target.value)}
            />
            <div className="flex flex-wrap items-center gap-4 text-sm">
                <label className="flex items-center gap-2 whitespace-nowrap">
                    {t('Address')}: /p/
                    <input
                        className={`${field} w-48 py-1`}
                        value={form.data.slug}
                        placeholder="privacy"
                        onChange={(e) => form.setData('slug', e.target.value.toLowerCase())}
                    />
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        className="accent-brand size-4"
                        checked={form.data.in_footer}
                        onChange={(e) => form.setData('in_footer', e.target.checked)}
                    />
                    {t('Link in the footer')}
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        className="accent-brand size-4"
                        checked={form.data.published}
                        onChange={(e) => form.setData('published', e.target.checked)}
                    />
                    {t('Published')}
                </label>
                <label className="flex items-center gap-2 whitespace-nowrap">
                    {t('Order')}
                    <input
                        type="number"
                        className={`${field} w-20 py-1`}
                        value={form.data.sort}
                        onChange={(e) => form.setData('sort', Number(e.target.value))}
                    />
                </label>
            </div>
            {Object.values(form.errors).length > 0 && <p className="text-sm text-red-600">{Object.values(form.errors)[0]}</p>}
            <div className="flex flex-wrap items-center justify-end gap-2">
                {edit.slug && (
                    <>
                        <button type="button" className={`${secondary} me-auto`} onClick={remove} aria-label={t('Delete')}>
                            <Trash2 className="size-4" />
                        </button>
                        <a href={route('pages.show', edit.slug)} target="_blank" rel="noreferrer" className={secondary}>
                            <Eye className="size-4" /> {t('View')}
                        </a>
                        {canTranslate && missing.length > 0 && current.title.trim() && (
                            <button type="button" className={secondary} disabled={translating} onClick={translate}>
                                <Languages className="size-4" /> {translating ? t('Translating…') : t('Translate into the missing languages')}
                            </button>
                        )}
                    </>
                )}
                <button className={primary} disabled={form.processing}>
                    {t('Save')}
                </button>
            </div>
        </form>
    );
}
