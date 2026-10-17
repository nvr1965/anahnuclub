import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export function PostComposer({ groupId }: { groupId?: number }) {
    const t = useT();
    const getInitials = useInitials();
    const { auth } = usePage<SharedData>().props;
    const form = useForm({ body: '', group_id: groupId ?? null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('posts.store'), { preserveScroll: true, onSuccess: () => form.reset('body') });
    };

    return (
        <form onSubmit={submit} className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 dark:bg-neutral-900 dark:ring-neutral-800">
            <div className="flex gap-3">
                <Avatar className="size-10">
                    <AvatarImage src={auth.user?.avatar} alt="" className="object-cover" />
                    <AvatarFallback className="bg-brand-soft text-brand">{getInitials(auth.user?.name ?? '')}</AvatarFallback>
                </Avatar>
                <textarea
                    dir="auto"
                    rows={form.data.body ? 4 : 2}
                    className="focus:ring-brand/30 flex-1 resize-none rounded-xl bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 dark:bg-neutral-800"
                    placeholder={t('What is new?')}
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                />
            </div>
            {form.data.body.trim() && (
                <div className="mt-3 flex justify-end">
                    <button disabled={form.processing} className="bg-cta hover:bg-cta-dark rounded-full px-5 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {t('Publish')}
                    </button>
                </div>
            )}
        </form>
    );
}
