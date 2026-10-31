import { type Person } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/** What the viewer did with a person on this page, so the card answers at once (like Facebook's "Request sent"). */
export type FriendStatus = 'sent' | 'cancelled' | 'accepted' | 'declined' | 'dismissed';

export const softButton = 'bg-brand/10 text-brand hover:bg-brand/20 w-full rounded-md py-1.5 text-sm font-semibold';

/** Friend actions that keep the list as it is and only refresh the given props. */
export function useFriendActions(only: string[]) {
    const [status, setStatus] = useState<Record<number, FriendStatus>>({});
    const mark = (id: number, value: FriendStatus) => setStatus((prev) => ({ ...prev, [id]: value }));
    const options = (id: number, value: FriendStatus) => ({ preserveScroll: true, preserveState: true, only, onSuccess: () => mark(id, value) });

    return {
        status,
        add: (person: Person) => router.post(route('friends.store', person.id), {}, options(person.id, 'sent')),
        accept: (person: Person) => router.post(route('friends.store', person.id), {}, options(person.id, 'accepted')),
        cancel: (person: Person) => router.delete(route('friends.destroy', person.id), options(person.id, 'cancelled')),
        decline: (person: Person) => router.delete(route('friends.destroy', person.id), options(person.id, 'declined')),
        dismiss: (person: Person) => router.post(route('friends.dismiss', person.id), {}, options(person.id, 'dismissed')),
    };
}
