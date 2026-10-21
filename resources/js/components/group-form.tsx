import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { defaultImages } from '@/lib/default-images';
import { useT } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Plus, Settings } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

export interface EditableGroup {
    slug: string;
    name: string;
    description_text: string;
    is_private: boolean;
    avatar: string | null;
    cover: string | null;
}

const field =
    'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none focus:border-brand dark:border-neutral-700 dark:bg-neutral-900';

/** Creating a group, or (with `group`) its settings for the owner and admins. */
export function GroupForm({ group, isOwner = false }: { group?: EditableGroup; isOwner?: boolean }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        description: string;
        is_private: boolean;
        avatar: File | null;
        cover: File | null;
        remove_avatar: boolean;
        remove_cover: boolean;
    }>({
        name: group?.name ?? '',
        description: group?.description_text ?? '',
        is_private: group?.is_private ?? false,
        avatar: null,
        cover: null,
        remove_avatar: false,
        remove_cover: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, forceFormData: true, onSuccess: () => setOpen(false) };
        if (group) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(route('groups.update', group.slug), done);
        } else {
            form.post(route('groups.store'), done);
        }
    };

    const picture = (key: 'avatar' | 'cover', label: string, current: string | null) => {
        const file = form.data[key];
        const removeKey = key === 'avatar' ? 'remove_avatar' : 'remove_cover';
        const preview = file ? URL.createObjectURL(file) : form.data[removeKey] ? null : current;
        return (
            <div className="space-y-1.5">
                <span className="text-sm font-medium">{label}</span>
                <div className="flex items-center gap-3">
                    <img
                        src={preview ?? (key === 'avatar' ? defaultImages.group : defaultImages.groupCover)}
                        alt=""
                        className={`${key === 'avatar' ? 'size-14 rounded-xl' : 'h-14 w-36 rounded-md'} object-cover`}
                    />
                    <label className="text-brand inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold">
                        <ImagePlus className="size-4" /> {t('Choose a picture')}
                        <input
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => {
                                form.setData(key, e.target.files?.[0] ?? null);
                                form.setData(removeKey, false);
                            }}
                        />
                    </label>
                    {preview && (
                        <button
                            type="button"
                            className="text-muted-foreground text-sm hover:underline"
                            onClick={() => {
                                form.setData(key, null);
                                form.setData(removeKey, true);
                            }}
                        >
                            {t('Remove')}
                        </button>
                    )}
                </div>
                {form.errors[key] && <p className="text-sm text-red-600">{form.errors[key]}</p>}
            </div>
        );
    };

    return (
        <>
            {group ? (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="inline-flex items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                >
                    <Settings className="size-4" /> {t('Settings')}
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="inline-flex items-center gap-2 rounded-full bg-slate-200 px-4 py-2 text-sm font-semibold whitespace-nowrap hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                >
                    <Plus className="size-4" /> {t('Create group')}
                </button>
            )}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{group ? t('Group settings') : t('New group')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <label className="block space-y-1.5">
                            <span className="text-sm font-medium">{t('Group name')}</span>
                            <input
                                dir="auto"
                                required
                                maxLength={120}
                                className={field}
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            {form.errors.name && <p className="text-sm text-red-600">{form.errors.name}</p>}
                        </label>
                        <label className="block space-y-1.5">
                            <span className="text-sm font-medium">{t('Description')}</span>
                            <textarea
                                dir="auto"
                                rows={4}
                                maxLength={5000}
                                className={field}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                placeholder={t('What the group is about, who it is for')}
                            />
                        </label>
                        {picture('avatar', t('Group picture'), group?.avatar ?? null)}
                        {picture('cover', t('Cover'), group?.cover ?? null)}
                        <label className="flex items-start gap-2 text-[15px]">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.is_private}
                                onChange={(e) => form.setData('is_private', e.target.checked)}
                            />
                            <span>
                                {t('Closed group')}
                                <span className="text-muted-foreground block text-sm">
                                    {t('Posts are seen only by members; joining needs approval.')}
                                </span>
                            </span>
                        </label>
                        <div className="flex flex-wrap items-center justify-between gap-2 pt-2">
                            {group && isOwner ? (
                                <button
                                    type="button"
                                    className="text-sm font-semibold text-red-600 hover:underline"
                                    onClick={() =>
                                        window.confirm(
                                            t('Delete the group “:name” with all its posts? This cannot be undone.', { name: group.name }),
                                        ) && router.delete(route('groups.destroy', group.slug))
                                    }
                                >
                                    {t('Delete group')}
                                </button>
                            ) : (
                                <span />
                            )}
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="bg-brand hover:bg-brand-dark rounded-md px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                            >
                                {group ? t('Save') : t('Create')}
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
