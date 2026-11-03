import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { shrinkImage } from '@/lib/shrink-image';
import { useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactNode } from 'react';

export interface EditableEvent {
    id: number;
    title: string;
    description_text: string | null;
    location: string | null;
    starts_at: string | null;
    ends_at: string | null;
    group_id: number | null;
    cover: string | null;
}

/** "2026-10-18T21:00" in the reader's own time zone, as a datetime-local field wants it. */
const toLocalInput = (iso: string | null) => {
    if (!iso) return '';
    const d = new Date(iso);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};

/** Create or edit an event; times are entered in the reader's time zone and stored in UTC. */
export function EventForm({
    event,
    groups,
    open,
    onClose,
}: {
    event?: EditableEvent;
    groups: { id: number; name: string }[];
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm<{
        title: string;
        description: string;
        location: string;
        starts_at: string;
        ends_at: string;
        group_id: string;
        cover: File | null;
        remove_cover: boolean;
    }>({
        title: event?.title ?? '',
        description: event?.description_text ?? '',
        location: event?.location ?? '',
        starts_at: toLocalInput(event?.starts_at ?? null),
        ends_at: toLocalInput(event?.ends_at ?? null),
        group_id: event?.group_id ? String(event.group_id) : '',
        cover: null,
        remove_cover: false,
    });
    const field =
        'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            starts_at: data.starts_at ? new Date(data.starts_at).toISOString() : '',
            ends_at: data.ends_at ? new Date(data.ends_at).toISOString() : null,
            group_id: data.group_id || null,
            description: data.description || null,
            location: data.location || null,
        }));
        form.post(event ? route('events.update', event.id) : route('events.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const row = (label: string, error: string | undefined, input: ReactNode) => (
        <label className="block space-y-1">
            <span className="text-sm font-medium">{label}</span>
            {input}
            {error && <span className="block text-sm text-red-600">{error}</span>}
        </label>
    );

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{event ? t('Edit event') : t('Create event')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-3">
                    {row(
                        t('Event name'),
                        form.errors.title,
                        <input dir="auto" className={field} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />,
                    )}
                    <div className="grid gap-3 sm:grid-cols-2">
                        {row(
                            t('Starts'),
                            form.errors.starts_at,
                            <input
                                type="datetime-local"
                                className={field}
                                value={form.data.starts_at}
                                onChange={(e) => form.setData('starts_at', e.target.value)}
                            />,
                        )}
                        {row(
                            t('Ends'),
                            form.errors.ends_at,
                            <input
                                type="datetime-local"
                                className={field}
                                value={form.data.ends_at}
                                onChange={(e) => form.setData('ends_at', e.target.value)}
                            />,
                        )}
                    </div>
                    {row(
                        t('Place'),
                        form.errors.location,
                        <input dir="auto" className={field} value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />,
                    )}
                    {row(
                        t('Description'),
                        form.errors.description,
                        <textarea
                            dir="auto"
                            rows={4}
                            className={field}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />,
                    )}
                    {groups.length > 0 &&
                        row(
                            t('Group'),
                            form.errors.group_id,
                            <select className={field} value={form.data.group_id} onChange={(e) => form.setData('group_id', e.target.value)}>
                                <option value="">{t('No group, open to everyone')}</option>
                                {groups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.name}
                                    </option>
                                ))}
                            </select>,
                        )}
                    {row(
                        t('Cover picture'),
                        form.errors.cover,
                        <input
                            type="file"
                            accept="image/*"
                            className={field}
                            onChange={async (e) => form.setData('cover', e.target.files?.[0] ? await shrinkImage(e.target.files[0]) : null)}
                        />,
                    )}
                    {event?.cover && (
                        <label className="text-muted-foreground flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="accent-brand size-4"
                                checked={form.data.remove_cover}
                                onChange={(e) => form.setData('remove_cover', e.target.checked)}
                            />
                            {t('Remove picture')}
                        </label>
                    )}
                    <div className="flex justify-end">
                        <button
                            className="bg-brand hover:bg-brand-dark rounded-md px-6 py-2 text-sm font-semibold text-white disabled:opacity-40"
                            disabled={form.processing || !form.data.title.trim() || !form.data.starts_at}
                        >
                            {event ? t('Save') : t('Create')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
