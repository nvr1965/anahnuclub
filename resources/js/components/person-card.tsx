import { UserAvatar } from '@/components/user-avatar';
import { type Person } from '@/types';
import { Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

export const primary = 'bg-brand hover:bg-brand-dark w-full rounded-md py-1.5 text-sm font-semibold text-white';
export const secondary =
    'w-full rounded-md bg-slate-200 py-1.5 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700';

export function PersonCard({ person, children }: { person: Person; children: ReactNode }) {
    return (
        <div className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
            <Link href={route('people.show', person.id)} className="relative block">
                {person.avatar ? (
                    <img src={person.avatar} alt="" loading="lazy" className="aspect-square w-full object-cover" />
                ) : (
                    <UserAvatar
                        person={{ ...person, online: false }}
                        className="aspect-square size-auto w-full rounded-none"
                        fallbackClassName="rounded-none text-4xl"
                    />
                )}
                {person.online && (
                    <span className="absolute end-2 bottom-2 size-4 rounded-full bg-green-500 ring-2 ring-white dark:ring-neutral-900" />
                )}
            </Link>
            <div className="space-y-1.5 p-3">
                <Link href={route('people.show', person.id)} dir="auto" className="block truncate text-[15px] font-semibold hover:underline">
                    {person.name}
                </Link>
                {children}
            </div>
        </div>
    );
}
