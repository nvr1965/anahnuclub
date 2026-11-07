import { card } from '@/components/admin-ui';
import { UserBadge } from '@/components/user-badge';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

type Measure = 'days' | 'posts' | 'comments' | 'reactions';
type Thresholds = Record<string, Record<Measure, number>>;

const MEASURES: { key: Measure; label: string }[] = [
    { key: 'days', label: 'Days in the club' },
    { key: 'posts', label: 'Own posts' },
    { key: 'comments', label: 'Comments' },
    { key: 'reactions', label: 'Reactions to their posts' },
];

/** Levels earned by posting, what each asks for, and the special badges given by hand. */
export default function AdminBadges({
    thresholds,
    levels,
    special,
}: {
    thresholds: Thresholds;
    levels: { slug: string; count: number }[];
    special: { slug: string; count: number }[];
}) {
    const t = useT();
    const form = useForm<{ thresholds: Thresholds }>({ thresholds });
    const set = (level: string, m: Measure, value: string) =>
        form.setData('thresholds', { ...form.data.thresholds, [level]: { ...form.data.thresholds[level], [m]: Math.max(0, Number(value) || 0) } });
    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.badges.update'), { preserveScroll: true });
    };
    const holders = (slug: string, count: number) => (
        <Link href={route('admin.users.index', { badge: slug })} className="text-brand text-sm hover:underline">
            {t(':count members', { count })}
        </Link>
    );

    return (
        <AdminLayout title={t('Badges and levels')}>
            <section className={`${card} mb-4 p-4`}>
                <h2 className="mb-1 text-lg font-semibold">{t('Levels')}</h2>
                <p className="text-muted-foreground mb-3 text-sm">
                    {t(
                        'A level is counted by itself from what a member posts. Every number of a level must be reached. Only members who confirmed their email and accepted the rules rise above Site user.',
                    )}
                </p>
                <form onSubmit={save}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-start">
                                    <th className="py-2 pe-3 text-start font-medium">{t('Level')}</th>
                                    {MEASURES.map((m) => (
                                        <th key={m.key} className="px-2 py-2 text-start font-medium">
                                            {t(m.label)}
                                        </th>
                                    ))}
                                    <th className="py-2 ps-2 text-start font-medium">{t('Holders')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y dark:divide-neutral-800">
                                {levels.map((l) => (
                                    <tr key={l.slug}>
                                        <td className="py-2 pe-3">
                                            <UserBadge badge={l.slug} />
                                        </td>
                                        {MEASURES.map((m) => (
                                            <td key={m.key} className="px-2 py-2">
                                                {form.data.thresholds[l.slug] ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        value={form.data.thresholds[l.slug][m.key]}
                                                        onChange={(e) => set(l.slug, m.key, e.target.value)}
                                                        className="w-20 rounded-md border px-2 py-1 dark:border-neutral-700 dark:bg-neutral-800"
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </td>
                                        ))}
                                        <td className="py-2 ps-2 whitespace-nowrap">{holders(l.slug, l.count)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                        className="bg-brand mt-3 rounded-full px-4 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {t('Save and count levels again')}
                    </button>
                    {form.recentlySuccessful && <span className="text-muted-foreground ms-3 text-sm">{t('Saved')}</span>}
                </form>
            </section>
            <section className={`${card} p-4`}>
                <h2 className="mb-1 text-lg font-semibold">{t('Special badges')}</h2>
                <p className="text-muted-foreground mb-3 text-sm">
                    {t(
                        'Given by an administrator on the Users page, button Badges. Beside the name goes the first special badge a member holds, otherwise their level.',
                    )}
                </p>
                <ul className="divide-y dark:divide-neutral-800">
                    {special.map((s) => (
                        <li key={s.slug} className="flex items-center justify-between gap-3 py-2">
                            <UserBadge badge={s.slug} />
                            {holders(s.slug, s.count)}
                        </li>
                    ))}
                </ul>
            </section>
        </AdminLayout>
    );
}
