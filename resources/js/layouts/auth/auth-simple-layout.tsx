import ClubLayout from '@/layouts/club-layout';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <ClubLayout>
            <div className="mx-auto mt-6 w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200/70 dark:bg-neutral-900 dark:ring-neutral-800">
                {/* The logo on white, as in the top bar: the banner picture carries the logo's own teal background,
                    which clashed with the card's colour in every style but Club (orange in Forum, rose in Showcase). */}
                <div className="from-header-from via-brand to-header-to h-1 bg-gradient-to-r" />
                <div className="flex flex-col items-center gap-2 pt-8">
                    <img src="/images/logo-mark.png" alt="" className="size-16 rounded-full" />
                    <span className="text-brand text-2xl font-bold tracking-tight">anahnu.club</span>
                </div>
                <div className="space-y-6 px-6 pt-4 pb-6 sm:px-8 sm:pb-8">
                    <div className="space-y-1 text-center">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        <p className="text-muted-foreground text-sm">{description}</p>
                    </div>
                    {children}
                </div>
            </div>
        </ClubLayout>
    );
}
