import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { flagUrl } from '@/lib/flags';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown } from 'lucide-react';

type Language = SharedData['languages'][number];

const choose = (code: string) => router.post(route('locale.update'), { locale: code }, { preserveScroll: true });

/** A small flag picture; where none fits, the language code in a grey box. */
export function LanguageFlag({ code, className = 'h-3.5 w-5' }: { code: string; className?: string }) {
    const url = flagUrl(code);
    return url ? (
        <img src={url} alt="" aria-hidden="true" className={`${className} shrink-0 rounded-[2px] object-cover shadow-[0_0_0_1px_rgba(0,0,0,0.12)]`} />
    ) : (
        <span
            aria-hidden="true"
            className={`${className} inline-flex shrink-0 items-center justify-center rounded-[2px] bg-slate-200 text-[8px] font-bold text-slate-600 uppercase`}
        >
            {code.slice(0, 2)}
        </span>
    );
}

function Items({ languages, locale }: { languages: Language[]; locale: string }) {
    return languages.map((language) => (
        <DropdownMenuItem key={language.code} onSelect={() => language.code !== locale && choose(language.code)} className="gap-3 py-2">
            <LanguageFlag code={language.code} />
            <span className="flex-1">
                <bdi>{language.native_name}</bdi>
            </span>
            {language.code === locale && <Check className="text-brand size-4" />}
        </DropdownMenuItem>
    ));
}

/** The site language: flag and name, opening a short list right under it. */
export function LanguageSwitcher({ className = '' }: { className?: string }) {
    const t = useT();
    const { locale, languages } = usePage<SharedData>().props;
    const current = languages.find((l) => l.code === locale);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label={t('Language')}
                className={`inline-flex items-center gap-2 text-sm font-medium outline-none ${className}`}
            >
                <LanguageFlag code={locale} />
                <span className="lang-name">{current?.native_name ?? locale}</span>
                <ChevronDown className="size-3.5 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="max-h-[70vh] min-w-44 overflow-y-auto">
                <Items languages={languages} locale={locale} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The same list as a submenu, for the user menu on phones. */
export function LanguageSubmenu() {
    const t = useT();
    const { locale, languages } = usePage<SharedData>().props;
    const current = languages.find((l) => l.code === locale);

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger className="gap-2">
                <LanguageFlag code={locale} />
                <span className="flex-1">{t('Language')}</span>
                <span className="text-muted-foreground text-xs">{current?.native_name}</span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="max-h-[70vh] min-w-44 overflow-y-auto">
                <Items languages={languages} locale={locale} />
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
