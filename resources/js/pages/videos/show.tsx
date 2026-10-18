import { VideoPlayer } from '@/components/post-media';
import { ShareMenu } from '@/components/share-menu';
import { TranslatedText } from '@/components/translated-text';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import { VideoGrid } from '@/components/video-grid';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Person, type SharedData, type TranslationInfo, type VideoItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Pencil, ThumbsUp, Trash2 } from 'lucide-react';
import { type FormEventHandler, useMemo, useState } from 'react';

interface Video extends VideoItem {
    description: string;
    description_text: string | null;
    author: Person | null;
    mine: boolean;
    can_delete: boolean;
    likes_count: number;
    liked: boolean;
    url: string;
    source_url: string | null;
    translation: TranslationInfo | null;
}

const button = 'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold transition hover:bg-slate-100 dark:hover:bg-neutral-800';

export default function VideoShow({ video, more }: { video: Video; more: VideoItem[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [editing, setEditing] = useState(false);
    const items = useMemo(() => [video], [video]);
    const translated = useAutoTranslate(items, locale);

    const remove = () => {
        if (window.confirm(t('Delete this video?'))) router.delete(route('videos.destroy', video.id));
    };

    return (
        <AppLayout>
            <Head title={video.title} />
            <div className="mx-auto max-w-[960px] space-y-4">
                <article className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
                    <VideoPlayer video={video} />
                    <div className="space-y-3 p-4">
                        <TranslatedText
                            item={video}
                            fetched={translated('video', video.id)}
                            original={{ title: video.title, body: video.description }}
                            bodyField="description"
                            titleClassName="text-xl font-bold"
                        />
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-neutral-800">
                            {video.author && (
                                <Link href={route('people.show', video.author.id)} className="flex items-center gap-2">
                                    <UserAvatar person={video.author} className="size-9" />
                                    <span>
                                        <span className="block text-[15px] font-semibold">{video.author.name}</span>
                                        <span className="text-muted-foreground block text-[13px]">{relativeTime(video.created_at, locale)}</span>
                                    </span>
                                </Link>
                            )}
                            <div className="flex flex-wrap items-center gap-1">
                                <button
                                    type="button"
                                    className={`${button} ${video.liked ? 'text-brand' : 'text-muted-foreground'}`}
                                    onClick={() => router.post(route('videos.like', video.id), {}, { preserveScroll: true })}
                                >
                                    <ThumbsUp className={`size-5 ${video.liked ? 'fill-current' : ''}`} />{' '}
                                    {video.likes_count > 0 ? video.likes_count : t('Like')}
                                </button>
                                <ShareMenu url={video.url} title={video.title} className={`${button} text-muted-foreground outline-none`} />
                                {video.source_url && (
                                    <a
                                        href={video.source_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className={`${button} text-muted-foreground`}
                                        title={video.provider === 'youtube' ? 'YouTube' : 'Vimeo'}
                                    >
                                        <ExternalLink className="size-5" />
                                    </a>
                                )}
                                {video.mine && (
                                    <button
                                        type="button"
                                        className={`${button} text-muted-foreground`}
                                        onClick={() => setEditing(true)}
                                        aria-label={t('Edit')}
                                    >
                                        <Pencil className="size-5" />
                                    </button>
                                )}
                                {video.can_delete && (
                                    <button type="button" className={`${button} text-muted-foreground`} onClick={remove} aria-label={t('Delete')}>
                                        <Trash2 className="size-5" />
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </article>
                {more.length > 0 && video.author && (
                    <section className="rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900">
                        <h2 className="mb-3 text-lg font-bold">{t('More from :name', { name: video.author.name })}</h2>
                        <VideoGrid videos={more} />
                    </section>
                )}
            </div>
            {video.mine && <EditVideo video={video} open={editing} onClose={() => setEditing(false)} />}
        </AppLayout>
    );
}

function EditVideo({ video, open, onClose }: { video: Video; open: boolean; onClose: () => void }) {
    const t = useT();
    const form = useForm({ title: video.title, description: video.description_text ?? '' });
    const field =
        'focus:border-brand w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none dark:border-neutral-700 dark:bg-neutral-900';
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('videos.update', video.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Edit')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-3">
                    <input dir="auto" className={field} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    <textarea
                        dir="auto"
                        rows={4}
                        className={field}
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                    <div className="flex justify-end">
                        <button
                            className="bg-brand hover:bg-brand-dark rounded-md px-6 py-2 text-sm font-semibold text-white"
                            disabled={form.processing || !form.data.title.trim()}
                        >
                            {t('Save')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
