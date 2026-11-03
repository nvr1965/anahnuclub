import { DropdownMenuItem, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from '@/components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { skinInfo, useSkin } from '@/lib/skins';
import { cn } from '@/lib/utils';
import { type Skin } from '@/types';
import { Check, Palette } from 'lucide-react';

/** A tiny drawing of each style's page, made of boxes (no pictures to download): where the menu sits, how wide the feed is, what a post looks like. */
const bar = (w: string, h = 'h-1') => <span className={`block ${h} ${w} shrink-0 rounded-full bg-slate-300`} />;
const dot = (c: string, size = 'size-2') => <span className={`${size} shrink-0 rounded-full ${c}`} />;
const photo = (h = 'h-6') => <span className={`block ${h} w-full shrink-0 rounded-sm bg-gradient-to-br from-amber-200 to-rose-300`} />;

function Preview({ skin }: { skin: Skin }) {
    const frame = 'flex h-24 overflow-hidden rounded-md';

    switch (skin) {
        case 'stream':
            return (
                <div aria-hidden className={`${frame} gap-1 bg-white p-1`}>
                    <span className="flex w-7 shrink-0 flex-col gap-1 pt-0.5">
                        {dot('bg-[#0f172a]')}
                        {bar('w-6')}
                        {bar('w-5')}
                        {bar('w-6')}
                        {bar('w-4')}
                        <span className="mt-1 h-2 w-6 rounded-full bg-[#0f172a]" />
                    </span>
                    <span className="flex flex-1 flex-col border-x border-slate-200">
                        {[0, 1].map((i) => (
                            <span key={i} className="flex gap-1 border-b border-slate-200 p-1">
                                {dot('bg-slate-400')}
                                <span className="flex flex-1 flex-col gap-0.5">
                                    {bar('w-3/4')}
                                    {i === 0 && photo('h-5')}
                                </span>
                            </span>
                        ))}
                    </span>
                    <span className="flex w-6 shrink-0 flex-col gap-1">
                        <span className="h-2 rounded-full border border-slate-300" />
                        <span className="h-8 rounded-sm border border-slate-200" />
                    </span>
                </div>
            );
        case 'showcase':
            return (
                <div aria-hidden className={`${frame} bg-white`}>
                    <span className="flex w-3.5 shrink-0 flex-col items-center gap-1.5 border-e border-slate-200 pt-2">
                        {[0, 1, 2, 3].map((i) => (
                            <span key={i} className="size-1.5 rounded-sm bg-slate-400" />
                        ))}
                    </span>
                    <span className="flex flex-1 justify-center gap-1.5 p-1">
                        <span className="flex w-12 flex-col gap-0.5">
                            <span className="flex gap-0.5">
                                {[0, 1, 2].map((i) => (
                                    <span key={i} className="size-2.5 rounded-full border border-[#c0265e]" />
                                ))}
                            </span>
                            <span className="flex items-center gap-0.5">
                                {dot('bg-slate-400', 'size-1.5')}
                                {bar('w-5')}
                            </span>
                            {photo('h-11')}
                        </span>
                        <span className="flex w-6 flex-col gap-1 pt-1">
                            {bar('w-6')}
                            {bar('w-4')}
                            {bar('w-5')}
                        </span>
                    </span>
                </div>
            );
        case 'forum':
            return (
                <div aria-hidden className={`${frame} flex-col bg-[#eef1f5]`}>
                    <span className="flex items-center gap-1 border-b border-slate-200 bg-white px-1 py-1">
                        {dot('bg-[#d9480f]')}
                        <span className="mx-auto h-2 w-1/2 rounded-full bg-slate-200" />
                    </span>
                    <span className="flex flex-1 gap-1 p-1">
                        <span className="flex w-6 flex-col gap-1 border-e border-slate-200 bg-white p-0.5">
                            {bar('w-4')}
                            {bar('w-5')}
                            {bar('w-3')}
                        </span>
                        <span className="flex flex-1 flex-col gap-1">
                            {[0, 1].map((i) => (
                                <span key={i} className="flex flex-col gap-0.5 rounded-md border border-slate-200 bg-white p-1">
                                    {bar('w-3/4', 'h-1.5')}
                                    <span className="flex gap-0.5">
                                        <span className="h-1.5 w-3 rounded-full bg-slate-200" />
                                        <span className="h-1.5 w-3 rounded-full bg-slate-200" />
                                    </span>
                                </span>
                            ))}
                        </span>
                        <span className="h-10 w-5 rounded-md bg-white" />
                    </span>
                </div>
            );
        default: {
            // Club, Community and Large text share the frame: top bar with tiles, menu, cards, people.
            const blue = skin === 'community';
            const large = skin === 'large';
            return (
                <div aria-hidden className={`${frame} flex-col ${blue ? 'bg-[#edeef0]' : 'bg-[#f0f2f5]'}`}>
                    <span className={`flex items-center justify-center gap-1 px-1 py-1 ${blue ? 'bg-[#3a6ea8]' : 'bg-white'}`}>
                        {[0, 1, 2, 3].map((i) => (
                            <span
                                key={i}
                                className={`size-2 rounded-sm ${blue ? 'bg-white/70' : ['bg-[#117892]', 'bg-emerald-400', 'bg-violet-400', 'bg-sky-400'][i]}`}
                            />
                        ))}
                    </span>
                    <span className="flex flex-1 gap-1 p-1">
                        <span className="flex w-5 flex-col gap-1 pt-0.5">
                            {bar('w-5')}
                            {bar('w-4')}
                            {bar('w-5')}
                        </span>
                        <span className="flex flex-1 flex-col gap-1">
                            {Array.from({ length: blue ? 3 : large ? 1 : 2 }, (_, i) => (
                                <span key={i} className={`flex gap-1 bg-white p-1 ${blue ? 'rounded-sm' : 'rounded-md shadow-sm'}`}>
                                    {dot(blue ? 'bg-[#3a6ea8]' : 'bg-[#117892]', large ? 'size-4' : 'size-2')}
                                    <span className="flex flex-1 flex-col gap-0.5">
                                        {bar('w-3/4', large ? 'h-2' : 'h-1')}
                                        {bar('w-1/2', large ? 'h-2' : 'h-1')}
                                        {large && bar('w-2/3', 'h-2')}
                                    </span>
                                </span>
                            ))}
                        </span>
                        <span className="flex w-5 flex-col gap-1 pt-0.5">
                            {dot('bg-slate-400')}
                            {dot('bg-slate-400')}
                        </span>
                    </span>
                </div>
            );
        }
    }
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
