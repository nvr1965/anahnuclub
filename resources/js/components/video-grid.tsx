import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type SharedData, type VideoItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Film, Play } from 'lucide-react';

/** Video thumbnails with titles, linking to each video's page. */
export function VideoGrid({ videos }: { videos: VideoItem[] }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {videos.map((video) => (
                <Link key={video.id} href={route('videos.show', video.id)} className="group min-w-0">
                    <div className="relative flex aspect-video items-center justify-center overflow-hidden rounded-lg bg-slate-800">
                        {video.thumb ? (
                            <img
                                src={video.thumb}
                                alt=""
                                loading="lazy"
                                onError={(e) => (e.currentTarget.style.display = 'none')}
                                className="size-full object-cover transition group-hover:scale-105"
                            />
                        ) : (
                            <Film className="size-10 text-white/60" />
                        )}
                        <span className="absolute inset-0 flex items-center justify-center opacity-0 transition group-hover:opacity-100">
                            <span className="flex size-12 items-center justify-center rounded-full bg-black/60 text-white">
                                <Play className="ms-0.5 size-6 fill-current" />
                            </span>
                        </span>
                    </div>
                    <div dir="auto" className="mt-1.5 line-clamp-2 text-[15px] leading-snug font-semibold">
                        {video.title}
                    </div>
                    <div className="text-muted-foreground text-[13px]">
                        {video.author && <>{video.author.name} · </>}
                        {relativeTime(video.created_at, locale)}
                        {!video.author && video.provider !== 'file' && <> · {video.provider === 'youtube' ? 'YouTube' : 'Vimeo'}</>}
                        {!video.author && video.provider === 'file' && <> · {t('Uploaded')}</>}
                    </div>
                </Link>
            ))}
        </div>
    );
}
