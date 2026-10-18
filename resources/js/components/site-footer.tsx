import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Heart } from 'lucide-react';

/** Bottom of every page: site pages, the feedback form and the donation button (a menu when there are several ways to give). */
export function SiteFooter() {
    const t = useT();
    const { footer } = usePage<SharedData>().props;
    const link = 'hover:text-brand hover:underline';
    const donate =
        'inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3.5 py-1.5 text-[13px] font-semibold text-slate-900 shadow-sm transition hover:bg-amber-300';

    return (
        <footer className="text-muted-foreground mx-auto flex max-w-[1320px] flex-wrap items-center justify-center gap-x-5 gap-y-3 px-4 pt-2 pb-8 text-[13px]">
            {footer?.pages.map((page) => (
                <Link key={page.slug} href={route('pages.show', page.slug)} className={link} dir="auto">
                    {page.title}
                </Link>
            ))}
            <Link href={route('contact')} className={link}>
                {t('Contact us')}
            </Link>
            {footer?.donate.length === 1 && (
                <a href={footer.donate[0].url} target="_blank" rel="noopener noreferrer" className={donate}>
                    <Heart className="size-4 fill-current text-rose-600" /> {t('Support Anahnu Club')}
                </a>
            )}
            {(footer?.donate.length ?? 0) > 1 && (
                <DropdownMenu>
                    <DropdownMenuTrigger className={`${donate} outline-none`}>
                        <Heart className="size-4 fill-current text-rose-600" /> {t('Support Anahnu Club')}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent side="top" align="center" className="min-w-48">
                        {footer.donate.map((link) => (
                            <DropdownMenuItem key={link.url} asChild>
                                <a href={link.url} target="_blank" rel="noopener noreferrer" className="cursor-pointer font-medium">
                                    {link.label}
                                </a>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
            <span>© {new Date().getFullYear()} Anahnu.Club</span>
        </footer>
    );
}
