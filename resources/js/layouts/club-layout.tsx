import { ClubHeader } from '@/components/club-header';
import { type ReactNode } from 'react';

/** Page frame: header, then content on a soft grey background. An optional side column sits at the start. */
export default function ClubLayout({ children, aside }: { children: ReactNode; aside?: ReactNode }) {
    return (
        <div className="min-h-screen bg-slate-100 text-slate-900 dark:bg-neutral-950 dark:text-neutral-100">
            <ClubHeader />
            <main className="mx-auto max-w-6xl px-4 py-6">
                {aside ? (
                    <div className="grid gap-6 lg:grid-cols-[300px_1fr]">
                        <aside className="order-2 space-y-6 lg:order-1">{aside}</aside>
                        <div className="order-1 min-w-0 lg:order-2">{children}</div>
                    </div>
                ) : (
                    children
                )}
            </main>
        </div>
    );
}
