import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { shrinkImage } from '@/lib/shrink-image';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { ImagePlus, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

const MAX_PHOTOS = 20;

/** "What's on your mind?" box: a pill that opens into a text area, with pictures if wanted. */
export function PostComposer({ groupId }: { groupId?: number }) {
    const t = useT();
    const getInitials = useInitials();
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [preparing, setPreparing] = useState(false);
    const picker = useRef<HTMLInputElement>(null);
    const form = useForm<{ body: string; group_id: number | null; images: File[] }>({ body: '', group_id: groupId ?? null, images: [] });
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const previews = useMemo(() => form.data.images.map((file) => URL.createObjectURL(file)), [form.data.images]);

    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);

    const addPictures = async (files: FileList | null) => {
        if (!files?.length) return;
        setOpen(true);
        setPreparing(true);
        const shrunk = await Promise.all(
            Array.from(files)
                .slice(0, MAX_PHOTOS)
                .map((file) => shrinkImage(file)),
        );
        form.setData('images', [...form.data.images, ...shrunk].slice(0, MAX_PHOTOS));
        setPreparing(false);
        if (picker.current) picker.current.value = '';
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('posts.store'), {
            preserveScroll: true,
            forceFormData: form.data.images.length > 0,
            onSuccess: () => {
                form.reset('body', 'images');
                setOpen(false);
            },
        });
    };

    const empty = !form.data.body.trim() && form.data.images.length === 0;
    const photoButton = (
        <button
            type="button"
            onClick={() => picker.current?.click()}
            className="text-muted-foreground inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
        >
            <ImagePlus className="size-5 text-green-600" /> {t('Photo')}
        </button>
    );

    return (
        <form onSubmit={submit} className="rounded-lg bg-white px-4 py-3 shadow-sm dark:bg-neutral-900">
            <input ref={picker} type="file" accept="image/*" multiple className="hidden" onChange={(e) => addPictures(e.target.files)} />
            <div className="flex items-start gap-2">
                <Avatar className="size-10">
                    <AvatarImage src={auth.user?.avatar} alt="" className="object-cover" />
                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(auth.user?.name ?? '')}</AvatarFallback>
                </Avatar>
                {open ? (
                    <textarea
                        dir="auto"
                        autoFocus
                        rows={form.data.images.length ? 2 : 4}
                        className="flex-1 resize-none rounded-xl bg-slate-100 px-4 py-2.5 text-[15px] outline-none dark:bg-neutral-800"
                        placeholder={t("What's on your mind, :name?", { name: firstName })}
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="text-muted-foreground flex-1 rounded-full bg-slate-100 px-4 py-2.5 text-start text-[15px] hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                    >
                        {t("What's on your mind, :name?", { name: firstName })}
                    </button>
                )}
            </div>

            {previews.length > 0 && (
                <div className="mt-3 grid grid-cols-4 gap-1.5 sm:grid-cols-5">
                    {previews.map((url, i) => (
                        <div key={url} className="relative">
                            <img src={url} alt="" className="aspect-square w-full rounded-lg object-cover" />
                            <button
                                type="button"
                                aria-label={t('Remove')}
                                onClick={() =>
                                    form.setData(
                                        'images',
                                        form.data.images.filter((_, j) => j !== i),
                                    )
                                }
                                className="absolute end-1 top-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
            {form.errors.images && <p className="mt-2 text-sm text-red-600">{form.errors.images}</p>}
            {Object.keys(form.errors).some((k) => k.startsWith('images.')) && (
                <p className="mt-2 text-sm text-red-600">{t('One of the pictures could not be used.')}</p>
            )}

            {open ? (
                <div className="mt-3 flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-neutral-800">
                    {photoButton}
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => (form.reset('body', 'images'), setOpen(false))}
                            className="text-muted-foreground rounded-md px-4 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-neutral-800"
                        >
                            {t('Cancel')}
                        </button>
                        <button
                            disabled={form.processing || preparing || empty}
                            className="bg-brand hover:bg-brand-dark rounded-md px-6 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        >
                            {form.processing ? `${form.progress?.percentage ?? 0}%` : t('Publish')}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="mt-2 flex border-t border-slate-200 pt-2 dark:border-neutral-800">{photoButton}</div>
            )}
        </form>
    );
}
