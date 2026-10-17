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
                <div className="bg-brand flex items-center justify-center py-6">
                    <img src="/images/logo-banner.png" alt="anahnu.club" className="h-20 w-auto" />
                </div>
                <div className="space-y-6 p-6 sm:p-8">
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
