import FriendsLayout, { type FriendsMenuCounts } from '@/components/friends/friends-layout';
import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ListChecks, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ListItem {
    id: number;
    name: string;
    count: number;
    people: Person[];
}

interface Props {
    menu: FriendsMenuCounts;
    lists: ListItem[];
    current: { id: number; name: string; members: Person[]; others: Person[] } | null;
}

/** The member's own lists of friends (family, work, school...), seen only by them. */
export default function Lists({ menu, lists, current }: Props) {
    const t = useT();
    const create = useForm({ name: '' });
    const [renaming, setRenaming] = useState(false);
    const rename = useForm({ name: current?.name ?? '' });
    const [query, setQuery] = useState('');
    const keep = { preserveScroll: true, preserveState: true };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        create.post(route('friends.lists.store'), { onSuccess: () => create.reset() });
    };
    const submitRename = (e: FormEvent) => {
        e.preventDefault();
        if (current) rename.patch(route('friends.lists.update', current.id), { ...keep, onSuccess: () => setRenaming(false) });
    };
    const others = (current?.others ?? []).filter((p) => p.name.toLowerCase().includes(query.trim().toLowerCase()));
    const card = 'rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900';
    const input = 'w-full rounded-md border bg-transparent px-3 py-2 text-[15px] dark:border-neutral-700';

    return (
        <FriendsLayout active="lists" menu={menu}>
            <Head title={t('Custom lists')} />
            <h1 className="mb-4 text-2xl font-bold">{t('Custom lists')}</h1>
            <div className="grid gap-4 md:grid-cols-[280px_minmax(0,1fr)]">
                <div className={`${card} h-fit space-y-3`}>
                    <form onSubmit={submitCreate} className="flex gap-2">
                        <input
                            value={create.data.name}
                            onChange={(e) => create.setData('name', e.target.value)}
                            placeholder={t('New list')}
                            maxLength={80}
                            className={input}
                        />
                        <button
                            disabled={!create.data.name.trim() || create.processing}
                            className="bg-brand hover:bg-brand-dark shrink-0 rounded-md px-3 text-white disabled:opacity-50"
                            aria-label={t('Create list')}
                        >
                            <Plus className="size-5" />
                        </button>
                    </form>
                    {lists.length === 0 && (
                        <p className="text-muted-foreground text-sm">{t('Group your friends into lists: family, work, school.')}</p>
                    )}
                    <div className="space-y-1">
                        {lists.map((list) => (
                            <Link
                                key={list.id}
                                href={route('friends.lists', { list: list.id })}
                                preserveScroll
                                className={`flex items-center gap-3 rounded-lg p-2 ${current?.id === list.id ? 'bg-black/5 dark:bg-white/10' : 'hover:bg-black/5 dark:hover:bg-white/10'}`}
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-200 dark:bg-neutral-700">
                                    <ListChecks className="size-5" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span dir="auto" className="block truncate text-[15px] font-semibold">
                                        {list.name}
                                    </span>
                                    <span className="text-muted-foreground text-[13px]">{t(':count friends', { count: list.count })}</span>
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>

                {current && (
                    <div className="space-y-4">
                        <div className={card}>
                            <div className="mb-3 flex items-center gap-2">
                                {renaming ? (
                                    <form onSubmit={submitRename} className="flex flex-1 gap-2">
                                        <input
                                            value={rename.data.name}
                                            onChange={(e) => rename.setData('name', e.target.value)}
                                            maxLength={80}
                                            className={input}
                                            autoFocus
                                        />
                                        <button className="bg-brand rounded-md px-3 text-sm font-semibold text-white">{t('Save')}</button>
                                        <button type="button" onClick={() => setRenaming(false)} aria-label={t('Cancel')}>
                                            <X className="size-5" />
                                        </button>
                                    </form>
                                ) : (
                                    <>
                                        <h2 dir="auto" className="min-w-0 flex-1 truncate text-xl font-bold">
                                            {current.name}
                                        </h2>
                                        <button
                                            className="rounded-full p-2 hover:bg-black/5 dark:hover:bg-white/10"
                                            onClick={() => {
                                                rename.setData('name', current.name);
                                                setRenaming(true);
                                            }}
                                            aria-label={t('Rename')}
                                        >
                                            <Pencil className="size-4" />
                                        </button>
                                        <button
                                            className="rounded-full p-2 text-red-600 hover:bg-black/5 dark:hover:bg-white/10"
                                            onClick={() => {
                                                if (window.confirm(t('Delete this list? Your friends stay your friends.'))) {
                                                    router.delete(route('friends.lists.destroy', current.id));
                                                }
                                            }}
                                            aria-label={t('Delete list')}
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </>
                                )}
                            </div>
                            {current.members.length === 0 && (
                                <p className="text-muted-foreground text-[15px]">{t('No one in this list yet. Add friends below.')}</p>
                            )}
                            <div className="grid gap-1 sm:grid-cols-2">
                                {current.members.map((p) => (
                                    <div key={p.id} className="flex items-center gap-3 rounded-lg p-2 hover:bg-black/5 dark:hover:bg-white/10">
                                        <Link href={route('people.show', p.id)}>
                                            <UserAvatar person={p} className="size-11" />
                                        </Link>
                                        <Link
                                            href={route('people.show', p.id)}
                                            dir="auto"
                                            className="min-w-0 flex-1 truncate text-[15px] font-semibold hover:underline"
                                        >
                                            {p.name}
                                        </Link>
                                        <button
                                            className="text-muted-foreground rounded-full p-1.5 hover:bg-black/10 dark:hover:bg-white/10"
                                            onClick={() => router.delete(route('friends.lists.remove', [current.id, p.id]), keep)}
                                            aria-label={t('Remove from list')}
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className={card}>
                            <h3 className="mb-3 text-lg font-bold">{t('Add friends to the list')}</h3>
                            <div className="relative mb-3">
                                <Search className="text-muted-foreground absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder={t('Search friends')}
                                    className={`${input} ps-9`}
                                />
                            </div>
                            {others.length === 0 && <p className="text-muted-foreground text-[15px]">{t('No friends to add.')}</p>}
                            <div className="grid max-h-[480px] gap-1 overflow-y-auto sm:grid-cols-2">
                                {others.map((p) => (
                                    <button
                                        key={p.id}
                                        onClick={() => router.post(route('friends.lists.add', [current.id, p.id]), {}, keep)}
                                        className="flex items-center gap-3 rounded-lg p-2 text-start hover:bg-black/5 dark:hover:bg-white/10"
                                    >
                                        <UserAvatar person={p} className="size-11" />
                                        <span dir="auto" className="min-w-0 flex-1 truncate text-[15px] font-semibold">
                                            {p.name}
                                        </span>
                                        <Plus className="text-brand size-5" />
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </FriendsLayout>
    );
}
