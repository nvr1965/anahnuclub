import { ActionButton, Badge, card, type Page, Pager, send } from '@/components/admin-ui';
import AdminLayout from '@/layouts/admin-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Status = 'new' | 'approved' | 'posted' | 'skipped';

interface Channel {
    id: number;
    user: { id: number; name: string; username: string | null } | null;
    topic: string | null;
    group: { name: string; slug: string } | null;
    daily_min: number | null;
    daily_limit: number;
    stop_words: string | null;
    auto_approve: boolean;
    paused: boolean;
    waiting: number;
    approved: number;
    posted_today: number;
    plan: string[];
    posts_week: number;
    likes_week: number;
    comments_week: number;
    requests: { id: number; name: string }[];
    invitations: { id: number; group: string; slug: string; from: string | null }[];
}

interface Source {
    id: number;
    channel_id: number;
    name: string;
    url: string;
    locale: string | null;
    trusted: boolean;
    active: boolean;
    fetched_at: string | null;
    error: string | null;
    posted: number;
}

interface Item {
    id: number;
    title: string | null;
    summary: string | null;
    url: string | null;
    image: string | null;
    source: string | null;
    author: string | null;
    status: Status;
    reason: string | null;
    published_at: string | null;
    posted_at: string | null;
    post_id: number | null;
}

interface Props {
    tab: 'queue' | 'authors' | 'sources';
    status: Status;
    author: number | null;
    paused: boolean;
    members_busy: boolean;
    window: [number, number];
    channels: Channel[];
    sources: Source[];
    items: Page<Item> | null;
}

const input = 'rounded-md border border-slate-200 bg-transparent px-2 py-1 text-sm dark:border-neutral-700';
const chip = (on: boolean) => `rounded-full px-3 py-1 text-sm font-medium shadow-sm ${on ? 'bg-brand text-white' : 'bg-white dark:bg-neutral-900'}`;

/** Feed filling: the club's authors, their sources, and the queue a curator approves. */
export default function AdminFeeds(props: Props) {
    const t = useT();
    const { tab, paused, members_busy, window: hours } = props;

    return (
        <AdminLayout
            title={t('Feed filling')}
            actions={
                <button
                    type="button"
                    className={`rounded-full px-4 py-1.5 text-sm font-semibold ${paused ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`}
                    onClick={() => send('patch', route('admin.feeds.pause'), { paused: !paused })}
                >
                    {paused ? t('Resume all authors') : t('Pause all authors')}
                </button>
            }
        >
            <p className="text-muted-foreground mb-3 text-sm">
                {t(
                    'Club authors post by themselves from :from:00 to :to:00, Israel time: up to three newest items a source each run, each author its main news at its own times. What is not posted within 12 hours leaves the queue.',
                    {
                        from: hours[0],
                        to: hours[1],
                    },
                )}{' '}
                {paused && <b className="text-red-600">{t('Posting is paused.')}</b>}
                {members_busy && !paused && <b>{t('Members wrote a lot today, so authors post half as much.')}</b>}
            </p>
            <div className="mb-4 flex flex-wrap gap-2">
                {(['queue', 'authors', 'sources'] as const).map((name) => (
                    <Link key={name} href={route('admin.feeds.index', { tab: name })} className={chip(tab === name)}>
                        {{ queue: t('Queue'), authors: t('Authors'), sources: t('Sources') }[name]}
                    </Link>
                ))}
            </div>
            {tab === 'queue' && <Queue {...props} />}
            {tab === 'authors' && <Authors channels={props.channels} />}
            {tab === 'sources' && <Sources channels={props.channels} sources={props.sources} />}
        </AdminLayout>
    );
}

function Queue({ items, status, author, channels }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [selected, setSelected] = useState<number[]>([]);
    const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const setStatus = (ids: number[], to: Status) => {
        send('post', route('admin.feeds.items.status'), { ids, status: to });
        setSelected([]);
    };
    const filter = (params: Record<string, unknown>) =>
        router.get(route('admin.feeds.index', { tab: 'queue', status, author: author ?? undefined, ...params }));
    const labels: Record<Status, string> = { approved: t('In queue'), posted: t('Posted'), new: t('Waiting'), skipped: t('Skipped') };
    const page = items!;

    return (
        <>
            <ManualForm channels={channels} />
            <div className="mb-3 flex flex-wrap items-center gap-2">
                {(Object.keys(labels) as Status[]).map((s) => (
                    <button key={s} type="button" className={chip(status === s)} onClick={() => filter({ status: s })}>
                        {labels[s]}
                    </button>
                ))}
                <select className={input} value={author ?? ''} onChange={(e) => filter({ author: e.target.value || undefined })}>
                    <option value="">{t('All authors')}</option>
                    {channels.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.user?.name} {c.waiting ? `(${c.waiting})` : ''}
                        </option>
                    ))}
                </select>
                {selected.length > 0 && (
                    <>
                        <ActionButton onClick={() => setStatus(selected, 'approved')}>{t('Approve :count', { count: selected.length })}</ActionButton>
                        <ActionButton danger onClick={() => setStatus(selected, 'skipped')}>
                            {t('Skip :count', { count: selected.length })}
                        </ActionButton>
                    </>
                )}
                {page.data.length > 0 && status !== 'posted' && (
                    <ActionButton onClick={() => setSelected(selected.length === page.data.length ? [] : page.data.map((i) => i.id))}>
                        {selected.length === page.data.length ? t('Clear selection') : t('Select all')}
                    </ActionButton>
                )}
            </div>
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {page.data.length === 0 && <p className="text-muted-foreground p-6 text-center">{t('Nothing found.')}</p>}
                {page.data.map((item) => (
                    <div key={item.id} className="flex gap-3 p-3">
                        {item.status !== 'posted' && (
                            <input
                                type="checkbox"
                                className="accent-brand mt-1 size-4 shrink-0"
                                checked={selected.includes(item.id)}
                                onChange={() => toggle(item.id)}
                            />
                        )}
                        {item.image && <img src={item.image} alt="" loading="lazy" className="size-16 shrink-0 rounded object-cover" />}
                        <div className="min-w-0 flex-1">
                            <div className="text-muted-foreground text-xs">
                                <b className="text-foreground">{item.author}</b>
                                {item.source && <> · {item.source}</>}
                                {item.published_at && <> · {relativeTime(item.published_at, locale)}</>}
                                {item.reason && (
                                    <>
                                        {' '}
                                        <Badge tone="red">{item.reason}</Badge>
                                    </>
                                )}
                            </div>
                            {item.title && (
                                <div dir="auto" className="mt-0.5 font-medium">
                                    {item.url ? (
                                        <a href={item.url} target="_blank" rel="noreferrer" className="hover:underline">
                                            {item.title}
                                        </a>
                                    ) : (
                                        item.title
                                    )}
                                </div>
                            )}
                            {item.summary && (
                                <div dir="auto" className="text-muted-foreground mt-0.5 line-clamp-3 text-sm whitespace-pre-line">
                                    {item.summary}
                                </div>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-1">
                            {item.status === 'posted' && item.post_id ? (
                                <Link href={route('posts.show', item.post_id)} className="text-brand text-xs font-medium">
                                    {t('Open post')}
                                </Link>
                            ) : (
                                <>
                                    {item.status !== 'approved' && (
                                        <ActionButton onClick={() => setStatus([item.id], 'approved')}>{t('Approve')}</ActionButton>
                                    )}
                                    {item.status !== 'skipped' && (
                                        <ActionButton danger onClick={() => setStatus([item.id], 'skipped')}>
                                            {t('Skip')}
                                        </ActionButton>
                                    )}
                                    <ActionButton onClick={() => send('post', route('admin.feeds.items.publish', item.id))}>
                                        {t('Post now')}
                                    </ActionButton>
                                </>
                            )}
                        </div>
                    </div>
                ))}
            </div>
            <Pager page={page} />
        </>
    );
}

/** A curator's own items: recipes, folk jokes, a question to readers; several at once separated by a line "---". */
function ManualForm({ channels }: { channels: Channel[] }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [channel, setChannel] = useState(channels[0]?.id ?? 0);
    const [text, setText] = useState('');
    const [url, setUrl] = useState('');

    if (!open) {
        return (
            <button type="button" className="text-brand mb-3 text-sm font-medium" onClick={() => setOpen(true)}>
                + {t('Add by hand (jokes, recipes, a question to readers)')}
            </button>
        );
    }

    return (
        <form
            className={`${card} mb-4 space-y-2 p-3`}
            onSubmit={(e) => {
                e.preventDefault();
                router.post(
                    route('admin.feeds.items.store'),
                    { feed_channel_id: channel, text, url: url || null },
                    { preserveScroll: true, onSuccess: () => (setText(''), setUrl('')) },
                );
            }}
        >
            <select className={input} value={channel} onChange={(e) => setChannel(Number(e.target.value))}>
                {channels.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.user?.name} · {c.topic}
                    </option>
                ))}
            </select>
            <textarea
                className={`${input} block min-h-32 w-full`}
                dir="auto"
                value={text}
                onChange={(e) => setText(e.target.value)}
                placeholder={t('Text of the post. Several posts at once: separate them with a line of three dashes ---')}
            />
            <input className={`${input} block w-full`} value={url} onChange={(e) => setUrl(e.target.value)} placeholder={t('Link (optional)')} />
            <p className="text-muted-foreground text-xs">
                {t('Only your own texts or folk jokes in full; from other sites, a short quote and the link.')}
            </p>
            <div className="flex gap-2">
                <button type="submit" className="bg-brand rounded-md px-3 py-1.5 text-sm font-semibold text-white" disabled={!text.trim()}>
                    {t('Add to the queue')}
                </button>
                <button type="button" className="text-muted-foreground text-sm" onClick={() => setOpen(false)}>
                    {t('Cancel')}
                </button>
            </div>
        </form>
    );
}

function Authors({ channels }: { channels: Channel[] }) {
    const t = useT();
    const update = (c: Channel, data: Partial<Channel>) => send('patch', route('admin.feeds.authors.update', c.id), data);

    return (
        <div className={`${card} divide-y dark:divide-neutral-800`}>
            {channels.length === 0 && (
                <p className="text-muted-foreground p-6 text-center">{t('No authors yet: run php artisan feeds:setup on the server.')}</p>
            )}
            {channels.map((c) => (
                <div key={c.id} className="space-y-2 p-3">
                    <div className="flex flex-wrap items-center gap-2">
                        {c.user && (
                            <Link href={route('people.show', c.user.id)} className="font-semibold hover:underline" dir="auto">
                                {c.user.name}
                            </Link>
                        )}
                        <span className="text-muted-foreground text-sm" dir="auto">
                            {c.topic}
                        </span>
                        {c.group && (
                            <Link href={route('groups.show', c.group.slug)} className="text-sm text-sky-600 hover:underline" dir="auto">
                                {t('Group: :name', { name: c.group.name })}
                            </Link>
                        )}
                        {c.paused && <Badge tone="red">{t('Paused')}</Badge>}
                        {c.auto_approve && <Badge tone="green">{t('Auto-approve')}</Badge>}
                    </div>
                    <div className="text-muted-foreground text-xs">
                        {t('Today :done of :limit', {
                            done: c.posted_today,
                            limit: c.daily_min !== null && c.daily_min < c.daily_limit ? `${c.daily_min}–${c.daily_limit}` : c.daily_limit,
                        })}{' '}
                        {c.plan.length > 0 && <>· {t('Times today: :times', { times: c.plan.join(', ') })} </>}·{' '}
                        {t(':count waiting', { count: c.waiting })} · {t(':count approved', { count: c.approved })} ·{' '}
                        {t('Week: :posts posts, :likes likes, :comments comments', {
                            posts: c.posts_week,
                            likes: c.likes_week,
                            comments: c.comments_week,
                        })}
                    </div>
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <label className="flex items-center gap-1">
                            {t('Posts a day, from')}
                            <input
                                type="number"
                                min={0}
                                max={50}
                                defaultValue={c.daily_min ?? c.daily_limit}
                                className={`${input} w-16`}
                                onBlur={(e) =>
                                    Number(e.target.value) !== (c.daily_min ?? c.daily_limit) && update(c, { daily_min: Number(e.target.value) })
                                }
                            />
                            {t('to')}
                            <input
                                type="number"
                                min={0}
                                max={50}
                                defaultValue={c.daily_limit}
                                className={`${input} w-16`}
                                onBlur={(e) => Number(e.target.value) !== c.daily_limit && update(c, { daily_limit: Number(e.target.value) })}
                            />
                        </label>
                        <label className="flex items-center gap-1">
                            <input
                                type="checkbox"
                                className="accent-brand"
                                checked={c.auto_approve}
                                onChange={() => update(c, { auto_approve: !c.auto_approve })}
                            />
                            {t('Trusted sources without a check')}
                        </label>
                        <label className="flex items-center gap-1">
                            <input type="checkbox" className="accent-brand" checked={c.paused} onChange={() => update(c, { paused: !c.paused })} />
                            {t('Pause')}
                        </label>
                    </div>
                    {(c.requests.length > 0 || c.invitations.length > 0) && (
                        <div className="bg-brand-soft space-y-1 rounded-md p-2 text-sm">
                            {c.requests.map((r) => (
                                <div key={`f${r.id}`} className="flex flex-wrap items-center gap-2">
                                    <span dir="auto">{t(':name wants to be friends', { name: r.name })}</span>
                                    <ActionButton onClick={() => send('post', route('admin.feeds.authors.friend', [c.id, r.id]), { accept: true })}>
                                        {t('Accept')}
                                    </ActionButton>
                                    <ActionButton
                                        danger
                                        onClick={() => send('post', route('admin.feeds.authors.friend', [c.id, r.id]), { accept: false })}
                                    >
                                        {t('Decline')}
                                    </ActionButton>
                                </div>
                            ))}
                            {c.invitations.map((i) => (
                                <div key={`g${i.id}`} className="flex flex-wrap items-center gap-2">
                                    <span dir="auto">
                                        {t(':name invites to the group', { name: i.from ?? '—' })}{' '}
                                        <Link href={route('groups.show', i.slug)} className="font-medium hover:underline">
                                            {i.group}
                                        </Link>
                                    </span>
                                    <ActionButton
                                        onClick={() => send('post', route('admin.feeds.authors.invitation', [c.id, i.id]), { accept: true })}
                                    >
                                        {t('Accept')}
                                    </ActionButton>
                                    <ActionButton
                                        danger
                                        onClick={() => send('post', route('admin.feeds.authors.invitation', [c.id, i.id]), { accept: false })}
                                    >
                                        {t('Decline')}
                                    </ActionButton>
                                </div>
                            ))}
                        </div>
                    )}
                    <textarea
                        className={`${input} block w-full`}
                        rows={2}
                        dir="auto"
                        defaultValue={c.stop_words ?? ''}
                        placeholder={t('Stop words, one per line or separated by commas')}
                        onBlur={(e) => e.target.value !== (c.stop_words ?? '') && update(c, { stop_words: e.target.value })}
                    />
                </div>
            ))}
        </div>
    );
}

function Sources({ channels, sources }: { channels: Channel[]; sources: Source[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [draft, setDraft] = useState({ feed_channel_id: channels[0]?.id ?? 0, name: '', url: '', locale: '' });
    const name = (id: number) => channels.find((c) => c.id === id)?.user?.name ?? '—';

    return (
        <>
            <form
                className={`${card} mb-4 flex flex-wrap items-center gap-2 p-3`}
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(
                        route('admin.feeds.sources.store'),
                        { ...draft, locale: draft.locale || null },
                        {
                            preserveScroll: true,
                            onSuccess: () => setDraft({ ...draft, name: '', url: '', locale: '' }),
                        },
                    );
                }}
            >
                <select
                    className={input}
                    value={draft.feed_channel_id}
                    onChange={(e) => setDraft({ ...draft, feed_channel_id: Number(e.target.value) })}
                >
                    {channels.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.user?.name}
                        </option>
                    ))}
                </select>
                <input className={input} value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} placeholder={t('Name')} />
                <input
                    className={`${input} min-w-64 flex-1`}
                    value={draft.url}
                    onChange={(e) => setDraft({ ...draft, url: e.target.value })}
                    placeholder={t('RSS address, Telegram channel https://t.me/s/… or panet.com / bokra.net')}
                />
                <input
                    className={`${input} w-16`}
                    value={draft.locale}
                    onChange={(e) => setDraft({ ...draft, locale: e.target.value })}
                    placeholder="ru"
                />
                <button
                    type="submit"
                    className="bg-brand rounded-md px-3 py-1.5 text-sm font-semibold text-white"
                    disabled={!draft.name || !draft.url}
                >
                    {t('Add source')}
                </button>
            </form>
            <div className={`${card} divide-y dark:divide-neutral-800`}>
                {sources.map((s) => (
                    <div key={s.id} className="flex flex-wrap items-center gap-3 p-3">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <b>{name(s.channel_id)}</b> · <span dir="auto">{s.name}</span>
                                {s.locale && <Badge>{s.locale}</Badge>}
                                {s.trusted && <Badge tone="green">{t('Trusted')}</Badge>}
                                {!s.active && <Badge tone="red">{t('Off')}</Badge>}
                                {s.error && <Badge tone="red">{s.error}</Badge>}
                            </div>
                            <div className="text-muted-foreground truncate text-xs">
                                {s.url} · {s.fetched_at ? relativeTime(s.fetched_at, locale) : t('not read yet')} ·{' '}
                                {t(':count posted', { count: s.posted })}
                            </div>
                        </div>
                        <div className="flex gap-1">
                            <ActionButton onClick={() => send('post', route('admin.feeds.sources.fetch', s.id))}>{t('Read now')}</ActionButton>
                            <ActionButton onClick={() => send('patch', route('admin.feeds.sources.update', s.id), { trusted: !s.trusted })}>
                                {s.trusted ? t('Untrust') : t('Trust')}
                            </ActionButton>
                            <ActionButton onClick={() => send('patch', route('admin.feeds.sources.update', s.id), { active: !s.active })}>
                                {s.active ? t('Switch off') : t('Switch on')}
                            </ActionButton>
                            <ActionButton
                                danger
                                onClick={() =>
                                    confirm(t('Delete the source “:name”?', { name: s.name })) &&
                                    send('delete', route('admin.feeds.sources.destroy', s.id))
                                }
                            >
                                {t('Delete')}
                            </ActionButton>
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}
