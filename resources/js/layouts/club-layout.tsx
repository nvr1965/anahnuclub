import { ClubHeader } from '@/components/club-header';
import { SideRail } from '@/components/side-rail';
import { SiteFooter } from '@/components/site-footer';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type FormEventHandler, type ReactNode, useEffect, useState } from 'react';

type Props = {
    children: ReactNode;
    left?: ReactNode;
    right?: ReactNode;
    /** The page fills the screen under the top bar edge to edge, without the footer, as Messenger does. */
    full?: boolean;
    /** A page without side columns that should keep its full width (up to 1100px) whatever its content, such as a profile. */
    wide?: boolean;
};

/**
 * The frame of every member page. Its shape depends on the interface style (App\Support\Skins):
 * Club, Community, Large text: top bar, menu on the start side, content, people on the end side (Facebook-like).
 * Stream: no top bar on wide screens, a big menu on the start side, one framed column, search and people on the end side (X-like).
 * Showcase: a narrow strip of icons on the start side, a narrow feed, people on the end side (Instagram-like).
 * Forum: top bar with a wide search, menu on the start side behind a line, cards, people in a card on the end side (Reddit-like).
 * On phones every style has the top bar; Stream and Showcase move the section tiles to the bottom of the screen.
 */
export default function ClubLayout(props: Props) {
    const { skin } = usePage<SharedData>().props;

    if (skin === 'stream') return <StreamFrame {...props} />;
    if (skin === 'showcase') return <ShowcaseFrame {...props} />;
    if (skin === 'forum') return <ForumFrame {...props} />;

    return <ClubFrame {...props} />;
}

// Written out in full so Tailwind keeps them.
const railShows = { lg: 'lg:block', md: 'md:block' };
const headerHides = { lg: 'lg:hidden', md: 'md:hidden' };

function FullFrame({ children, rail }: { children: ReactNode; rail?: { variant: 'wide' | 'icons'; from: 'lg' | 'md' } }) {
    return (
        <div className="flex h-dvh bg-white text-slate-900 dark:bg-neutral-950 dark:text-neutral-100">
            {rail && (
                <aside
                    className={`hidden shrink-0 border-e border-slate-200 dark:border-neutral-800 ${railShows[rail.from]} ${rail.variant === 'wide' ? 'w-[88px] xl:w-[275px]' : 'w-[72px] xl:w-[244px]'}`}
                >
                    <SideRail variant={rail.variant} />
                </aside>
            )}
            <div className="flex min-w-0 flex-1 flex-col">
                <ClubHeader className={rail ? headerHides[rail.from] : ''} />
                <main className={`min-h-0 flex-1 ${rail ? 'max-sm:pb-16' : ''}`}>{children}</main>
            </div>
        </div>
    );
}

function ClubFrame({ children, left, right, full, wide }: Props) {
    if (full) return <FullFrame>{children}</FullFrame>;

    const columns =
        left && right
            ? 'lg:grid-cols-[280px_minmax(0,680px)] xl:grid-cols-[280px_minmax(0,680px)_300px]'
            : left
              ? 'lg:grid-cols-[280px_minmax(0,1fr)]'
              : wide
                ? 'grid-cols-[minmax(0,1100px)]'
                : '';

    return (
        <div className="bg-page min-h-screen text-slate-900 dark:text-neutral-100">
            <ClubHeader />
            <main className={`mx-auto grid max-w-[1320px] justify-center gap-6 px-3 py-4 sm:px-4 ${columns}`}>
                {left && (
                    <aside className="sticky top-[5.5rem] hidden max-h-[calc(100vh-6.375rem)] self-start overflow-y-auto lg:block">{left}</aside>
                )}
                <div className="min-w-0">{children}</div>
                {right && (
                    <aside className="sticky top-[5.5rem] hidden max-h-[calc(100vh-6.375rem)] self-start overflow-y-auto xl:block">{right}</aside>
                )}
            </main>
            <SiteFooter />
        </div>
    );
}

/** A search box for the end column of Stream and Showcase. */
function SearchBox({ className }: { className: string }) {
    const t = useT();
    const { url } = usePage();
    // On the search page the box keeps the words being searched for.
    const current = url.startsWith('/search') ? (new URLSearchParams(url.split('?')[1] ?? '').get('q') ?? '') : '';
    const [q, setQ] = useState(current);
    useEffect(() => setQ(current), [current]);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (q.trim()) router.get(route('search'), { q: q.trim() });
    };

    return (
        <form onSubmit={submit} className={`flex items-center gap-3 ${className}`}>
            <Search className="text-muted-foreground size-5 shrink-0" />
            <input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder={t('Search')}
                aria-label={t('Search anahnu.club')}
                data-search-box
                className="w-full min-w-0 bg-transparent text-[0.9375rem] outline-none"
            />
        </form>
    );
}

function StreamFrame({ children, right, full }: Props) {
    if (full) return <FullFrame rail={{ variant: 'wide', from: 'lg' }}>{children}</FullFrame>;

    return (
        <div className="bg-page min-h-screen text-slate-900 dark:text-neutral-100">
            <ClubHeader className="lg:hidden" />
            <div className="mx-auto flex max-w-[1280px] justify-center">
                <aside className="sticky top-0 hidden h-screen w-[88px] shrink-0 lg:block xl:w-[275px]">
                    <SideRail variant="wide" />
                </aside>
                <main className="min-h-screen w-full max-w-[600px] min-w-0 border-slate-200 max-sm:pb-20 sm:border-x dark:border-neutral-800">
                    {children}
                </main>
                <aside className="sticky top-0 hidden h-screen w-[350px] shrink-0 space-y-4 overflow-y-auto px-6 py-2 xl:block">
                    <SearchBox className="focus-within:border-brand rounded-full border border-slate-200 bg-white px-4 py-2.5 dark:border-neutral-800 dark:bg-black" />
                    {right && <div className="rounded-2xl border border-slate-200 py-3 dark:border-neutral-800">{right}</div>}
                    <SiteFooter />
                </aside>
            </div>
        </div>
    );
}

function ShowcaseFrame({ children, right, full }: Props) {
    const { auth } = usePage<SharedData>().props;
    if (full) return <FullFrame rail={{ variant: 'icons', from: 'md' }}>{children}</FullFrame>;

    return (
        <div className="bg-page min-h-screen text-slate-900 dark:text-neutral-100">
            <ClubHeader className="md:hidden" />
            <aside className="fixed inset-y-0 start-0 z-30 hidden w-[72px] border-e border-slate-200 bg-white md:block xl:w-[244px] dark:border-neutral-800 dark:bg-black">
                <SideRail variant="icons" />
            </aside>
            <div className="md:ms-[72px] xl:ms-[244px]">
                <div className="mx-auto flex max-w-[935px] justify-center gap-16 md:py-6">
                    <main className="w-full max-w-[470px] min-w-0 max-sm:pb-20">{children}</main>
                    <aside className="hidden w-[320px] shrink-0 space-y-4 pt-2 xl:block">
                        <SearchBox className="focus-within:border-brand rounded-lg border border-slate-200 bg-slate-100 px-4 py-2 dark:border-neutral-800 dark:bg-neutral-900" />
                        {auth.user && (
                            <div className="flex items-center gap-3 px-2">
                                <img src={auth.user.avatar ?? '/images/logo-mark.png'} alt="" className="size-11 rounded-full object-cover" />
                                <span dir="auto" className="truncate text-sm font-semibold">
                                    {auth.user.name}
                                </span>
                            </div>
                        )}
                        {right}
                        <SiteFooter />
                    </aside>
                </div>
            </div>
        </div>
    );
}

function ForumFrame({ children, left, right, full }: Props) {
    if (full) return <FullFrame>{children}</FullFrame>;

    return (
        <div className="bg-page min-h-screen text-slate-900 dark:text-neutral-100">
            <ClubHeader />
            <div className="mx-auto flex max-w-[1400px]">
                {left && (
                    <aside className="sticky top-[3.5rem] hidden h-[calc(100vh-3.5rem)] w-[272px] shrink-0 overflow-y-auto border-e border-slate-200 bg-white px-3 py-4 lg:block dark:border-neutral-800 dark:bg-neutral-950">
                        {left}
                    </aside>
                )}
                <div className="flex min-w-0 flex-1 justify-center gap-6 px-0 py-4 sm:px-6">
                    <main className="w-full max-w-[740px] min-w-0">{children}</main>
                    {right && (
                        <aside className="sticky top-[4.5rem] hidden max-h-[calc(100vh-5.5rem)] w-[316px] shrink-0 self-start overflow-y-auto rounded-2xl bg-white py-3 xl:block dark:bg-neutral-900">
                            {right}
                        </aside>
                    )}
                </div>
            </div>
            <SiteFooter />
        </div>
    );
}
