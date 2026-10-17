import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export function PostComposer({ groupId }: { groupId?: number }) {
    const t = useT();
    const form = useForm({ body: '', group_id: groupId ?? null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('posts.store'), { preserveScroll: true, onSuccess: () => form.reset('body') });
    };

    return (
        <form onSubmit={submit} className="border-sidebar-border/70 dark:border-sidebar-border space-y-2 rounded-xl border p-4">
            <textarea
                className="border-input min-h-20 w-full rounded-md border bg-transparent p-3 text-sm"
                placeholder={t('What is new?')}
                value={form.data.body}
                onChange={(e) => form.setData('body', e.target.value)}
            />
            <div className="flex justify-end">
                <Button disabled={form.processing || !form.data.body.trim()}>{t('Publish')}</Button>
            </div>
        </form>
    );
}
