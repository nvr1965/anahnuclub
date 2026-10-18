import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { VideoGrid } from '@/components/video-grid';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { videoFrame } from '@/lib/shrink-image';
import { type Paginated, type Person, type VideoItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Link2, Plus, Upload } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface Props {
    videos: Paginated<VideoItem>;
    author: Person | null;
}

export default function Videos({ videos, author }: Props) {
    const t = useT();
    const [adding, setAdding] = useState(() => new URLSearchParams(window.location.search).has('add'));

    return (
        <AppLayout>
            <Head title={t('Videos')} />
            <div className="space-y-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 dir="auto" className="text-2xl font-bold">
                        {author ? t('Videos of :name', { name: author.name }) : t('Videos')}
                    </h1>
                    <button
                        type="button"
                        onClick={() => setAdding(true)}
                        className="bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white"
                    >
                        <Plus className="size-4" /> {t('Add video')}
                    </button>
                </div>
                <section className="rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900">
                    {videos.data.length === 0 ? (
                        <p className="text-muted-foreground py-6 text-center text-[15px]">{t('No videos yet.')}</p>
                    ) : (
                        <VideoGrid videos={videos.data} />
                    )}
                </section>
                <nav className="text-brand flex justify-between px-1 text-sm font-medium">
                    {videos.links.prev ? <Link href={videos.links.prev}>{t('Newer')}</Link> : <span />}
                    {videos.links.next && <Link href={videos.links.next}>{t('Older')}</Link>}
                </nav>
            </div>
            <AddVideo open={adding} onClose={() => setAdding(false)} />
        </AppLayout>
    );
}

function AddVideo({ open, onClose }: { open: boolean; onClose: () => void }) {
    const t = useT();
    const [mode, setMode] = useState<'link' | 'file'>('link');
    const form = useForm<{ url: string; file: File | null; thumb: File | null; title: string; description: string; share: boolean }>({
        url: '',
        file: null,
        thumb: null,
        title: '',
        description: '',
        share: true,
    });
    const field =
        'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';
    const tab = (active: boolean) =>
        `flex-1 inline-flex items-center justify-center gap-2 rounded-md py-2 text-sm font-semibold ${active ? 'bg-white shadow-sm dark:bg-neutral-700' : 'text-muted-foreground'}`;

    const pickFile = async (file: File | undefined) => {
        if (!file) return;
        form.setData((data) => ({ ...data, file, title: data.title || file.name.replace(/\.\w+$/, '') }));
        const thumb = await videoFrame(file);
        form.setData((data) => ({ ...data, thumb }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => (mode === 'link' ? { ...data, file: null, thumb: null } : { ...data, url: '' }));
        form.post(route('videos.store'), { forceFormData: mode === 'file', onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && !form.processing && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Add video')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-3">
                    <div className="flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-neutral-800">
                        <button type="button" className={tab(mode === 'link')} onClick={() => setMode('link')}>
                            <Link2 className="size-4" /> {t('YouTube or Vimeo link')}
                        </button>
                        <button type="button" className={tab(mode === 'file')} onClick={() => setMode('file')}>
                            <Upload className="size-4" /> {t('Upload a file')}
                        </button>
                    </div>
                    {mode === 'link' ? (
                        <label className="block space-y-1">
                            <input
                                className={field}
                                type="url"
                                placeholder="https://www.youtube.com/watch?v=…"
                                value={form.data.url}
                                onChange={(e) => form.setData('url', e.target.value)}
                                autoFocus
                            />
                            {form.errors.url && <span className="text-sm text-red-600">{form.errors.url}</span>}
                        </label>
                    ) : (
                        <label className="block space-y-1">
                            <input
                                className={field}
                                type="file"
                                accept="video/mp4,video/webm,video/quicktime"
                                onChange={(e) => pickFile(e.target.files?.[0])}
                            />
                            <span className="text-muted-foreground block text-xs">{t('MP4, WebM or MOV, up to 200 MB.')}</span>
                            {form.errors.file && <span className="text-sm text-red-600">{form.errors.file}</span>}
                        </label>
                    )}
                    <label className="block space-y-1">
                        <span className="text-sm font-medium">{t('Title')}</span>
                        <input
                            dir="auto"
                            className={field}
                            value={form.data.title}
                            placeholder={mode === 'link' ? t('Taken from the video if left empty') : ''}
                            onChange={(e) => form.setData('title', e.target.value)}
                        />
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
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.share}
                            onChange={(e) => form.setData('share', e.target.checked)}
                            className="accent-brand size-4"
                        />
                        {t('Also show it in the feed')}
                    </label>
                    <div className="flex justify-end">
                        <button
                            className="bg-brand hover:bg-brand-dark rounded-md px-6 py-2 text-sm font-semibold text-white disabled:opacity-40"
                            disabled={form.processing || (mode === 'link' ? !form.data.url.trim() : !form.data.file)}
                        >
                            {form.processing && form.progress ? `${form.progress.percentage}%` : t('Publish')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
