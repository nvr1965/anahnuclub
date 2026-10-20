import { ActionButton, Badge, card, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';

type Category = 'drugs' | 'porn' | 'prostitution' | 'casino';

interface Item {
    key: string;
    type: 'post' | 'comment';
    id: number;
    title: string | null;
    excerpt: string;
    author: { id: number; name: string; blocked: boolean } | null;
    created_at: string | null;
    hits: Partial<Record<Category, string[]>>;
    url: string;
}

interface Props {
    items: Item[];
    total: number;
    counts: Record<Category, number>;
    authors: { id: number; name: string; blocked: boolean; count: number }[];
    category: Category | null;
    words: Record<Category, string>;
    builtin: Record<Category, string[]>;
}

/** The found words marked in the text. */
function highlight(text: string, words: string[]): ReactNode {
    if (words.length === 0) return text;
    const escaped = words.map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).sort((a, b) => b.length - a.length);
    const re = new RegExp(`(${escaped.join('|')})`, 'giu');
    return text.split(re).map((part, i) =>
        i % 2 === 1 ? (
            <mark key={i} className="rounded bg-amber-200 px-0.5 text-inherit dark:bg-amber-700">
                {part}
            </mark>
        ) : (
            part
        ),
    );
}

export default function AdminModeration({ items, total, counts, authors, category, words, builtin }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [selected, setSelected] = useState<string[]>([]);
    const [showWords, setShowWords] = useState(false);
    const names: Record<Category, string> = {
        drugs: t('Drugs and pills'),
        porn: t('Porn'),
        prostitution: t('Prostitution'),
        casino: t('Casino and betting'),
    };
    const form = useForm({ words });
    const ref = (key: string) => {
        const [type, id] = key.split(':');
        return { type, id: Number(id) };
    };
    const toggle = (key: string) => setSelected((s) => (s.includes(key) ? s.filter((x) => x !== key) : [...s, key]));
    const remove = (keys: string[]) => {
        if (!confirm(t('Delete :count posts? This cannot be undone.', { count: keys.length }))) return;
        send('delete', route('admin.moderation.destroy'), { items: keys.map(ref) });
        setSelected([]);
    };
    const keep = (keys: string[]) => {
        send('post', route('admin.moderation.keep'), { items: keys.map(ref) });
        setSelected([]);
    };
    const filter = (c: Category | null) => router.get(route('admin.moderation.index', { category: c ?? undefined }), {}, { preserveScroll: true });
    const tab = 'rounded-full px-3 py-1.5 text-sm font-medium aria-pressed:bg-brand aria-pressed:text-white bg-white dark:bg-neutral-900';

    return (
        <AdminLayout title={t('Content check')}>
            <p className="text-muted-foreground mb-3 text-sm">
                {t(
                    'Posts and comments with words about drugs, porn, prostitution or gambling, in any language of the club (translations are checked too). It is only a hint: open the post and decide. “All fine” hides it until it is edited.',
                )}
            </p>
            <div className="mb-4 flex flex-wrap gap-2">
                <button type="button" aria-pressed={category === null} onClick={() => filter(null)} className={tab}>
                    {t('All')} ({Object.values(counts).reduce((a, b) => a + b, 0)})
                </button>
                {(Object.keys(names) as Category[]).map((c) => (
                    <button key={c} type="button" aria-pressed={category === c} onClick={() => filter(c)} className={tab}>
                        {names[c]} ({counts[c]})
                    </button>
                ))}
                <button type="button" onClick={() => setShowWords(!showWords)} className="text-brand ms-auto text-sm font-medium hover:underline">
                    {t('Search words')}
                </button>
            </div>

            {showWords && (
                <form
                    className={`${card} mb-4 space-y-3 p-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('admin.moderation.words'), { preserveScroll: true });
                    }}
                >
                    <p className="text-muted-foreground text-sm">
                        {t('Built-in words are always searched. Add your own, separated by commas, in any language.')}
                    </p>
                    {(Object.keys(names) as Category[]).map((c) => (
                        <label key={c} className="block space-y-1">
                            <span className="text-sm font-semibold">{names[c]}</span>
                            <span dir="auto" className="text-muted-foreground block text-xs">
                                {builtin[c].join(', ')}
                            </span>
                            <textarea
                                dir="auto"
                                rows={2}
                                value={form.data.words[c]}
                                onChange={(e) => form.setData('words', { ...form.data.words, [c]: e.target.value })}
                                className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                            />
                        </label>
                    ))}
                    <button type="submit" disabled={form.processing} className="bg-brand rounded-md px-4 py-2 text-sm font-semibold text-white">
                        {t('Save and search again')}
                    </button>
                </form>
            )}

            {authors.length > 0 && (
                <div className={`${card} mb-4 p-3`}>
                    <div className="mb-2 text-sm font-semibold">{t('Authors')}</div>
                    <div className="flex flex-wrap gap-2">
                        {authors.map((a) => (
                            <span
                                key={a.id}
                                className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-1 ps-3 pe-1 text-sm dark:bg-neutral-800"
                            >
                                <Link href={route('people.show', a.id)} dir="auto" className="font-medium hover:underline">
                                    {a.name}
                                </Link>
                                <span className="text-muted-foreground">· {a.count}</span>
                                {a.blocked ? (
                                    <Badge tone="red">{t('Blocked')}</Badge>
                                ) : (
                                    <ActionButton
                                        danger
                                        onClick={() =>
                                            confirm(t('Block :name?', { name: a.name })) &&
                                            send('patch', route('admin.users.update', a.id), { blocked: true })
                                        }
                                    >
                                        {t('Block')}
                                    </ActionButton>
                                )}
                                <ActionButton danger onClick={() => remove(items.filter((i) => i.author?.id === a.id).map((i) => i.key))}>
                                    {t('Delete all')}
                                </ActionButton>
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {selected.length > 0 && (
                <div className="mb-3 flex flex-wrap gap-2 text-sm">
                    <button type="button" onClick={() => remove(selected)} className="rounded-full bg-red-600 px-3 py-1 font-medium text-white">
                        {t('Delete selected (:count)', { count: selected.length })}
                    </button>
                    <button type="button" onClick={() => keep(selected)} className="rounded-full bg-white px-3 py-1 font-medium dark:bg-neutral-900">
                        {t('All fine (:count)', { count: selected.length })}
                    </button>
                </div>
            )}

            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {items.length === 0 && <p className="text-muted-foreground p-6 text-center">{t('Nothing suspicious found.')}</p>}
                {items.map((item) => {
                    const found = Object.values(item.hits).flat() as string[];
                    return (
                        <div key={item.key} className="flex gap-3 p-3">
                            <input
                                type="checkbox"
                                className="accent-brand mt-1 size-4"
                                checked={selected.includes(item.key)}
                                onChange={() => toggle(item.key)}
                            />
                            <div className="min-w-0 flex-1">
                                <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                                    <Badge tone="gray">{item.type === 'post' ? t('Post') : t('Comment')}</Badge>
                                    {(Object.keys(item.hits) as Category[]).map((c) => (
                                        <Badge key={c} tone="red">
                                            {names[c]}
                                        </Badge>
                                    ))}
                                    {item.author ? (
                                        <Link
                                            href={route('people.show', item.author.id)}
                                            className="text-foreground font-medium hover:underline"
                                            dir="auto"
                                        >
                                            {item.author.name}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                    {item.created_at && <span>· {relativeTime(item.created_at, locale)}</span>}
                                </div>
                                {item.title && (
                                    <div dir="auto" className="mt-1 font-medium">
                                        {highlight(item.title, found)}
                                    </div>
                                )}
                                <div dir="auto" className="mt-1 text-sm">
                                    {highlight(item.excerpt, found)}
                                </div>
                                <div className="text-muted-foreground mt-1 text-xs" dir="auto">
                                    {t('Found')}: {found.join(', ')}
                                </div>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1">
                                <a
                                    href={item.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-brand rounded-md px-2.5 py-1 text-xs font-medium hover:underline"
                                >
                                    {t('Open')}
                                </a>
                                <ActionButton onClick={() => keep([item.key])}>{t('All fine')}</ActionButton>
                                <ActionButton danger onClick={() => remove([item.key])}>
                                    {t('Delete')}
                                </ActionButton>
                            </div>
                        </div>
                    );
                })}
            </div>
            {total > items.length && (
                <p className="text-muted-foreground mt-3 text-center text-sm">
                    {t('Showing :shown of :total. Deal with these and the rest will appear.', { shown: items.length, total })}
                </p>
            )}
        </AdminLayout>
    );
}
