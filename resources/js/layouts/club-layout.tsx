import { ClubHeader } from '@/components/club-header';
import { SiteFooter } from '@/components/site-footer';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

/**
 * Facebook-style frame: menu on the start side, content in the middle, people on the end side.
 * Side columns stick while the feed scrolls and fold away on small screens.
 */
export default function ClubLayout({
    children,
    left,
    right,
    full = false,
}: {
    children: ReactNode;
    left?: ReactNode;
    right?: ReactNode;
    /** The page fills the screen under the top bar edge to edge, without the footer, as Messenger does. */
    full?: boolean;
}) {
    const { skin } = usePage<SharedData>().props;
    // Stream and Showcase: one column of posts (plus the menu on wide screens) and a menu bar at the bottom on phones.
    const flat = skin === 'stream' || skin === 'showcase';

    if (full)
        return (
            <div className="flex h-dvh flex-col bg-white text-slate-900 dark:bg-neutral-950 dark:text-neutral-100">
                <ClubHeader />
                <main className={`min-h-0 flex-1 ${flat ? 'max-sm:pb-16' : ''}`}>{children}</main>
            </div>
        );

    const columns =
        left && right && flat
            ? 'lg:grid-cols-[280px_minmax(0,600px)]'
            : left && right
              ? 'lg:grid-cols-[280px_minmax(0,680px)] xl:grid-cols-[280px_minmax(0,680px)_300px]'
              : left
                ? 'lg:grid-cols-[280px_minmax(0,1fr)]'
                : '';

    return (
        <div className="bg-page min-h-screen text-slate-900 dark:text-neutral-100">
            <ClubHeader />
            <main
                className={`mx-auto grid max-w-[1320px] justify-center gap-6 px-3 py-4 sm:px-4 ${columns} ${flat ? 'max-sm:px-0 max-sm:pb-20' : ''}`}
            >
                {left && (
                    <aside className="sticky top-[5.5rem] hidden max-h-[calc(100vh-6.375rem)] self-start overflow-y-auto lg:block">{left}</aside>
                )}
                <div className="min-w-0">{children}</div>
                {right && !flat && (
                    <aside className="sticky top-[5.5rem] hidden max-h-[calc(100vh-6.375rem)] self-start overflow-y-auto xl:block">{right}</aside>
                )}
            </main>
            <SiteFooter />
        </div>
    );
}
