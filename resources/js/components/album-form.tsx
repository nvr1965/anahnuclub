import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

const secondary =
    'inline-flex items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700';

/** Create or rename an album. */
export function AlbumForm({
    album,
    open,
    onClose,
}: {
    album?: { id: number; name: string; description: string | null };
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm({ name: album?.name ?? '', description: album?.description ?? '' });
    const field =
        'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (album) form.patch(route('albums.update', album.id), { preserveScroll: true, onSuccess: onClose });
        else form.post(route('albums.store'), { onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{album ? t('Edit album') : t('New album')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block space-y-1">
                        <span className="text-sm font-medium">{t('Album name')}</span>
                        <input dir="auto" className={field} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                        {form.errors.name && <span className="text-sm text-red-600">{form.errors.name}</span>}
                    </label>
                    <label className="block space-y-1">
                        <span className="text-sm font-medium">{t('Description')}</span>
                        <textarea
                            dir="auto"
                            rows={3}
                            className={field}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </label>
                    <div className="flex justify-end gap-2">
                        <button type="button" className={secondary} onClick={onClose}>
                            {t('Cancel')}
                        </button>
                        <button
                            className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white"
                            disabled={form.processing || !form.data.name.trim()}
                        >
                            {album ? t('Save') : t('Create')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
