import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** "What's on your mind?" box: a pill that opens into a text area. */
export function PostComposer({ groupId }: { groupId?: number }) {
    const t = useT();
    const getInitials = useInitials();
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ body: '', group_id: groupId ?? null });
    const firstName = auth.user?.name.split(' ')[0] ?? '';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('posts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
                setOpen(false);
            },
        });
    };

    return (
        <form onSubmit={submit} className="rounded-lg bg-white px-4 py-3 shadow-sm dark:bg-neutral-900">
            <div className="flex items-start gap-2">
                <Avatar className="size-10">
                    <AvatarImage src={auth.user?.avatar} alt="" className="object-cover" />
                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(auth.user?.name ?? '')}</AvatarFallback>
                </Avatar>
                {open ? (
                    <textarea
                        dir="auto"
                        autoFocus
                        rows={4}
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
            {open && (
                <div className="mt-3 flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-neutral-800">
                    <button type="button" onClick={() => setOpen(false)} className="text-muted-foreground rounded-md px-4 py-2 text-sm font-semibold hover:bg-slate-100">
                        {t('Cancel')}
                    </button>
                    <button
                        disabled={form.processing || !form.data.body.trim()}
                        className="bg-brand hover:bg-brand-dark rounded-md px-6 py-2 text-sm font-semibold text-white disabled:opacity-40"
                    >
                        {t('Publish')}
                    </button>
                </div>
            )}
        </form>
    );
}
