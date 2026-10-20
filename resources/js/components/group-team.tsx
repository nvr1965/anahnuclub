import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Person } from '@/types';
import { router } from '@inertiajs/react';
import { Crown, MoreHorizontal, Shield, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export type GroupRole = 'owner' | 'admin' | 'moderator' | 'member';
type TeamRole = GroupRole | 'pending';
export type OfferRole = 'owner' | 'admin' | 'moderator';

export interface TeamMember extends Person {
    role: TeamRole;
    offer: { id: number; role: OfferRole } | null;
}

export function useRoleNames() {
    const t = useT();
    return { owner: t('Owner'), admin: t('Admin'), moderator: t('Moderator'), member: t('Member') } as Record<GroupRole, string>;
}

export function RoleBadge({ role }: { role: TeamRole }) {
    const t = useT();
    const names = useRoleNames();
    if (role === 'member') return null;
    if (role === 'pending')
        return <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{t('Wants to join')}</span>;
    const icon = { owner: <Crown className="size-3.5" />, admin: <ShieldCheck className="size-3.5" />, moderator: <Shield className="size-3.5" /> }[
        role
    ];
    return (
        <span className="bg-brand-soft text-brand inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold">
            {icon}
            {names[role]}
        </span>
    );
}

/** Who runs the group: offers to become admin or moderator, taking a role back, handing the group over. */
export function GroupTeam({ group, team, myRole }: { group: { slug: string; name: string }; team: TeamMember[]; myRole: GroupRole }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const opts = { preserveScroll: true, preserveState: true };
    const offer = (m: TeamMember, role: OfferRole) => router.post(route('groups.offers.store', group.slug), { user_id: m.id, role }, opts);
    const shown = team.filter((m) => m.name.toLowerCase().includes(query.trim().toLowerCase()));
    const offerText: Record<OfferRole, string> = {
        admin: t('Invited to be an admin'),
        moderator: t('Invited to be a moderator'),
        owner: t('Offered to take over the group'),
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700"
            >
                <ShieldCheck className="size-4" /> {t('Team')}
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('Group team')}</DialogTitle>
                        <DialogDescription>
                            {t('Admins and moderators can remove posts in the group. A role is given only after the member accepts the offer.')}
                        </DialogDescription>
                    </DialogHeader>
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Search by name')}
                        className="w-full rounded-full bg-slate-100 px-4 py-2 text-sm outline-none dark:bg-neutral-800"
                    />
                    <ul className="space-y-1">
                        {shown.map((m) => {
                            const canAct =
                                m.role !== 'owner' &&
                                (myRole === 'owner' ||
                                    (myRole === 'admin' && (m.role === 'member' || m.role === 'moderator' || m.role === 'pending')));
                            return (
                                <li key={m.id} className="flex items-center gap-3 rounded-lg p-1.5 hover:bg-slate-50 dark:hover:bg-neutral-800">
                                    <UserAvatar person={m} className="size-10" />
                                    <div className="min-w-0 flex-1">
                                        <div dir="auto" className="truncate text-[15px] font-medium">
                                            {m.name}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <RoleBadge role={m.role} />
                                            {m.offer && <span className="text-muted-foreground text-xs">{offerText[m.offer.role]}</span>}
                                        </div>
                                    </div>
                                    {m.role === 'pending' && (
                                        <button
                                            type="button"
                                            onClick={() => router.post(route('groups.members.approve', [group.slug, m.id]), {}, opts)}
                                            className="bg-brand hover:bg-brand-dark rounded-md px-3 py-1.5 text-sm font-semibold text-white"
                                        >
                                            {t('Accept')}
                                        </button>
                                    )}
                                    {canAct && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                className="rounded-full p-2 hover:bg-slate-200 dark:hover:bg-neutral-700"
                                                aria-label={t('Actions')}
                                            >
                                                <MoreHorizontal className="size-4" />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {m.role !== 'pending' && myRole === 'owner' && m.role !== 'admin' && (
                                                    <DropdownMenuItem onSelect={() => offer(m, 'admin')}>
                                                        {t('Invite to be an admin')}
                                                    </DropdownMenuItem>
                                                )}
                                                {m.role === 'member' && (
                                                    <DropdownMenuItem onSelect={() => offer(m, 'moderator')}>
                                                        {t('Invite to be a moderator')}
                                                    </DropdownMenuItem>
                                                )}
                                                {myRole === 'owner' && m.role !== 'pending' && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            window.confirm(
                                                                t(
                                                                    ':name will be offered to take over “:group”. You will stay on as an admin once they accept.',
                                                                    {
                                                                        name: m.name,
                                                                        group: group.name,
                                                                    },
                                                                ),
                                                            ) && offer(m, 'owner')
                                                        }
                                                    >
                                                        {t('Hand over the group')}
                                                    </DropdownMenuItem>
                                                )}
                                                {(m.role === 'admin' || m.role === 'moderator') && (
                                                    <DropdownMenuItem
                                                        onSelect={() => router.delete(route('groups.roles.destroy', [group.slug, m.id]), opts)}
                                                    >
                                                        {t('Take the role back')}
                                                    </DropdownMenuItem>
                                                )}
                                                {m.offer && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            router.delete(route('groups.offers.destroy', [group.slug, m.offer!.id]), opts)
                                                        }
                                                    >
                                                        {t('Cancel the offer')}
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuItem
                                                    className="text-red-600"
                                                    onSelect={() => router.delete(route('groups.members.destroy', [group.slug, m.id]), opts)}
                                                >
                                                    {m.role === 'pending' ? t('Decline') : t('Remove from the group')}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Shown to a member who was offered a role: accept or decline. */
export function OfferBanner({ group, offer }: { group: { slug: string; name: string }; offer: { id: number; role: OfferRole; from: Person } }) {
    const t = useT();
    const text = {
        admin: t(':name invites you to be an admin of this group.', { name: offer.from.name }),
        moderator: t(':name invites you to be a moderator of this group.', { name: offer.from.name }),
        owner: t(':name offers to hand this group over to you. You will become its owner.', { name: offer.from.name }),
    }[offer.role];

    return (
        <section className="border-brand/30 bg-brand-soft/50 flex flex-wrap items-center gap-3 rounded-lg border p-4">
            <UserAvatar person={offer.from} className="size-10" />
            <p className="min-w-0 flex-1 text-[15px]">{text}</p>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => router.post(route('groups.offers.accept', [group.slug, offer.id]), {}, { preserveScroll: true })}
                    className="bg-brand hover:bg-brand-dark rounded-md px-4 py-2 text-sm font-semibold text-white"
                >
                    {t('Accept')}
                </button>
                <button
                    type="button"
                    onClick={() => router.delete(route('groups.offers.destroy', [group.slug, offer.id]), { preserveScroll: true })}
                    className="rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800"
                >
                    {t('Decline')}
                </button>
            </div>
        </section>
    );
}
