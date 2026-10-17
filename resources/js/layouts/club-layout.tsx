import { ClubHeader } from '@/components/club-header';
import { type ReactNode } from 'react';

/**
 * Facebook-style frame: menu on the start side, content in the middle, people on the end side.
 * Side columns stick while the feed scrolls and fold away on small screens.
 */
export default function ClubLayout({ children, left, right }: { children: ReactNode; left?: ReactNode; right?: ReactNode }) {
    const columns = left && right ? 'lg:grid-cols-[280px_minmax(0,680px)] xl:grid-cols-[280px_minmax(0,680px)_300px]' : left ? 'lg:grid-cols-[280px_minmax(0,1fr)]' : '';

    return (
        <div className="min-h-screen bg-[#f0f2f5] text-slate-900 dark:bg-neutral-950 dark:text-neutral-100">
            <ClubHeader />
            <main className={`mx-auto grid max-w-[1320px] justify-center gap-6 px-3 py-4 sm:px-4 ${columns}`}>
                {left && <aside className="sticky top-[76px] hidden max-h-[calc(100vh-90px)] self-start overflow-y-auto lg:block">{left}</aside>}
                <div className="min-w-0">{children}</div>
                {right && <aside className="sticky top-[76px] hidden max-h-[calc(100vh-90px)] self-start overflow-y-auto xl:block">{right}</aside>}
            </main>
        </div>
    );
}
