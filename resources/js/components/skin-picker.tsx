import { DropdownMenuItem, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { skinInfo, useSkin } from '@/lib/skins';
import { cn } from '@/lib/utils';
import { type Skin } from '@/types';
import { Check, Palette } from 'lucide-react';

/** A tiny drawing of each style, made of boxes (no pictures to download): its colours, frames and where the photo goes. */
const looks: Record<Skin, { page: string; card: string; accent: string; layout: 'cards' | 'dense' | 'large' | 'lines' | 'photo' }> = {
    club: { page: 'bg-[#f0f2f5]', card: 'rounded-md bg-white shadow-sm', accent: 'bg-[#117892]', layout: 'cards' },
    community: { page: 'bg-[#edeef0]', card: 'rounded-sm bg-white', accent: 'bg-[#3a6ea8]', layout: 'dense' },
    large: { page: 'bg-[#f0f2f5]', card: 'rounded-md bg-white shadow-sm', accent: 'bg-[#117892]', layout: 'large' },
    stream: { page: 'bg-white', card: 'border-b border-slate-200 bg-white', accent: 'bg-[#0f172a]', layout: 'lines' },
    showcase: { page: 'bg-white', card: 'bg-white', accent: 'bg-[#c0265e]', layout: 'photo' },
};

function Preview({ skin }: { skin: Skin }) {
    const look = looks[skin];
    const line = (w: string, h = 'h-1') => <span className={`block ${h} ${w} rounded-full bg-slate-300`} />;

    return (
        <div aria-hidden className={`flex h-24 flex-col gap-1 overflow-hidden rounded-md p-1.5 ${look.page}`}>
            <span className={`block h-2 w-full shrink-0 rounded-sm ${look.accent}`} />
            {look.layout === 'photo' ? (
                <div className={`${look.card} flex flex-1 flex-col gap-1`}>
                    <span className="flex items-center gap-1">
                        <span className={`size-2.5 rounded-full ${look.accent}`} />
                        {line('w-8')}
                    </span>
                    <span className="block flex-1 bg-gradient-to-br from-amber-200 to-rose-300" />
                    {line('w-12')}
                </div>
            ) : (
                Array.from({ length: look.layout === 'dense' ? 3 : look.layout === 'large' ? 1 : 2 }, (_, i) => (
                    <div key={i} className={`${look.card} flex shrink-0 gap-1 p-1`}>
                        <span className={`${look.layout === 'large' ? 'size-4' : 'size-2.5'} shrink-0 rounded-full ${look.accent}`} />
                        <span className="flex flex-1 flex-col gap-1">
                            {line('w-3/4', look.layout === 'large' ? 'h-2' : 'h-1')}
                            {line('w-1/2', look.layout === 'large' ? 'h-2' : 'h-1')}
                            {look.layout === 'large' && line('w-2/3', 'h-2')}
                        </span>
                    </div>
                ))
            )}
        </div>
    );
}

/** The style choice on the Appearance settings page. */
export function SkinPicker() {
    const t = useT();
    const { skin: current, skins, setSkin } = useSkin();

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3" role="radiogroup" aria-label={t('Interface style')}>
            {skins.map((skin) => (
                <button
                    key={skin}
                    type="button"
                    role="radio"
                    aria-checked={skin === current}
                    onClick={() => skin !== current && setSkin(skin)}
                    className={cn(
                        'relative flex flex-col gap-2 rounded-xl border p-2 text-start transition-colors',
                        skin === current ? 'border-brand ring-brand ring-2' : 'border-slate-200 hover:border-slate-400 dark:border-neutral-700',
                    )}
                >
                    <Preview skin={skin} />
                    <span className="px-0.5 text-sm font-semibold">{t(skinInfo[skin].name)}</span>
                    <span className="text-muted-foreground px-0.5 text-xs leading-snug">{t(skinInfo[skin].about)}</span>
                    {skin === current && (
                        <span className="bg-brand absolute end-3 top-3 flex size-5 items-center justify-center rounded-full text-white">
                            <Check className="size-3.5" />
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}

/** The quick switch in the account menu. */
export function SkinSubmenu() {
    const t = useT();
    const { skin: current, skins, setSkin } = useSkin();
    if (skins.length < 2) return null;

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger className="gap-2">
                <Palette className="text-muted-foreground size-4" />
                <span className="flex-1">{t('Interface style')}</span>
                <span className="text-muted-foreground text-xs">{t(skinInfo[current].name)}</span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="min-w-44">
                {skins.map((skin) => (
                    <DropdownMenuItem key={skin} onSelect={() => skin !== current && setSkin(skin)} className="gap-3 py-2">
                        <span className="flex-1">{t(skinInfo[skin].name)}</span>
                        {skin === current && <Check className="text-brand size-4" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
