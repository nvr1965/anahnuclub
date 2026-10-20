import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';
import { router } from '@inertiajs/react';
import { Check, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Candidate extends Person {
    friend: boolean;
    invited: boolean;
}

/** "Invite to the group": pick club members (friends first) and send them an invitation. */
export function GroupInvite({ group }: { group: { slug: string; name: string } }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [people, setPeople] = useState<Candidate[] | null>(null);
    const [picked, setPicked] = useState<number[]>([]);
    const [sending, setSending] = useState(false);

    useEffect(() => {
        if (!open) return;
        const timer = setTimeout(() => {
            fetch(route('groups.invite.candidates', { group: group.slug, q: query }), { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then(setPeople)
                .catch(() => setPeople([]));
        }, 250);
        return () => clearTimeout(timer);
    }, [open, query, group.slug]);

    const toggle = (id: number) => setPicked((list) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]));
    const send = () => {
        setSending(true);
        router.post(
            route('groups.invite.store', group.slug),
            { user_ids: picked },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
                onSuccess: () => {
                    setPeople((list) => list?.map((p) => (picked.includes(p.id) ? { ...p, invited: true } : p)) ?? null);
                    setPicked([]);
                },
            },
        );
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap text-white"
            >
                <UserPlus className="size-4" /> {t('Invite to the group')}
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex max-h-[85dvh] flex-col sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('Invite to the group')}</DialogTitle>
                        <DialogDescription>{t('Choose club members. They get an invitation and decide whether to join.')}</DialogDescription>
                    </DialogHeader>
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Search by name')}
                        className="w-full rounded-full bg-slate-100 px-4 py-2 text-sm outline-none dark:bg-neutral-800"
                    />
                    <ul className="-mx-1 min-h-0 flex-1 space-y-0.5 overflow-y-auto px-1">
                        {people === null && <li className="text-muted-foreground py-6 text-center text-sm">…</li>}
                        {people?.length === 0 && <li className="text-muted-foreground py-6 text-center text-sm">{t('Nobody found')}</li>}
                        {people?.map((p) => {
                            const on = picked.includes(p.id);
                            return (
                                <li key={p.id}>
                                    <button
                                        type="button"
                                        disabled={p.invited}
                                        onClick={() => toggle(p.id)}
                                        className="flex w-full items-center gap-3 rounded-lg p-1.5 text-start hover:bg-slate-50 disabled:opacity-60 dark:hover:bg-neutral-800"
                                    >
                                        <UserAvatar person={p} className="size-10" />
                                        <span className="min-w-0 flex-1">
                                            <span dir="auto" className="block truncate text-[15px] font-medium">
                                                {p.name}
                                            </span>
                                            {(p.friend || p.invited) && (
                                                <span className="text-muted-foreground text-xs">{p.invited ? t('Invited') : t('Friend')}</span>
                                            )}
                                        </span>
                                        <span
                                            className={`flex size-6 items-center justify-center rounded-full border ${on || p.invited ? 'bg-brand border-brand text-white' : 'border-slate-300'}`}
                                        >
                                            {(on || p.invited) && <Check className="size-4" />}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                    <button
                        type="button"
                        disabled={picked.length === 0 || sending}
                        onClick={send}
                        className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {picked.length > 0 ? t('Send invitations (:count)', { count: picked.length }) : t('Send invitations')}
                    </button>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Shown to a club member invited into the group. */
export function InvitationBanner({ group, invitation }: { group: { slug: string }; invitation: { id: number; from: Person } }) {
    const t = useT();
    return (
        <section className="border-brand/30 bg-brand-soft/50 flex flex-wrap items-center gap-3 rounded-lg border p-4">
            <UserAvatar person={invitation.from} className="size-10" />
            <p className="min-w-0 flex-1 text-[15px]">{t(':name invites you to join this group.', { name: invitation.from.name })}</p>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => router.post(route('groups.invitations.accept', [group.slug, invitation.id]), {}, { preserveScroll: true })}
                    className="bg-brand hover:bg-brand-dark rounded-md px-4 py-2 text-sm font-semibold text-white"
                >
                    {t('Join')}
                </button>
                <button
                    type="button"
                    onClick={() => router.delete(route('groups.invitations.destroy', [group.slug, invitation.id]), { preserveScroll: true })}
                    className="rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800"
                >
                    {t('Decline')}
                </button>
            </div>
        </section>
    );
}
