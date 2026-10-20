import { AlbumForm } from '@/components/album-form';
import { AudienceIcon, useAudienceLabels } from '@/components/audience-picker';
import { CountryPicker } from '@/components/country-picker';
import { PhotoViewer } from '@/components/photo-viewer';
import { PostComposer } from '@/components/post-composer';
import { PostList } from '@/components/post-list';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { VideoGrid } from '@/components/video-grid';
import ClubLayout from '@/layouts/club-layout';
import { albumName } from '@/lib/albums';
import { countryFlag, countryName } from '@/lib/countries';
import { useT } from '@/lib/i18n';
import { relativeTime } from '@/lib/relative-time';
import { type Paginated, type Person, type PhotoItem, type Post, type SharedData, type VideoItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    Cake,
    Camera,
    Check,
    ChevronDown,
    Clock,
    Ellipsis,
    Flag,
    Globe,
    Images,
    MapPin,
    MessageCircle,
    MessageCircleOff,
    Pencil,
    Plus,
    UserCheck,
    UserPlus,
    UserX,
} from 'lucide-react';
import { type FormEventHandler, type ReactNode, useRef, useState } from 'react';

interface Profile {
    id: number;
    name: string;
    username: string | null;
    avatar: string | null;
    cover: string | null;
    status: string | null;
    about: string | null;
    location: string | null;
    location_translated?: string | null;
    country: string | null;
    website: string | null;
    birthdate: string | null;
    gender: 'male' | 'female' | null;
    joined_at: string | null;
    last_seen_at: string | null;
    friends_count: number | null;
    friends_hidden: boolean;
    privacy: Record<PrivateField, Visibility> | null;
    posts_count: number;
    is_me: boolean;
    friendship: 'none' | 'friends' | 'sent' | 'received' | null;
    blocked_by_me: boolean;
    messages_blocked_by_me: boolean;
    can_message: boolean;
}

type PrivateField = 'location' | 'country' | 'website' | 'birthdate' | 'gender' | 'friends';
type Visibility = 'public' | 'friends' | 'only_me';

type Photo = PhotoItem;
type Album = { id: number; name: string; kind: string | null; photos_count: number; cover: string | null };

interface Props {
    profile: Profile;
    tab: 'posts' | 'about' | 'friends' | 'photos' | 'videos';
    posts: Paginated<Post> | null;
    friends: Person[];
    photos: Photo[];
    albums: Album[];
    videos: VideoItem[];
}

const card = 'rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900';
const button = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-[15px] font-semibold transition';
const primary = `${button} bg-brand hover:bg-brand-dark text-white`;
const secondary = `${button} bg-slate-200 text-slate-900 hover:bg-slate-300 dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700`;

export default function PersonShow({ profile, tab, posts, friends, photos, albums, videos }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [editing, setEditing] = useState(false);
    const [creatingAlbum, setCreatingAlbum] = useState(false);
    const tabs: { key: Props['tab']; label: string }[] = [
        { key: 'posts', label: t('Posts') },
        { key: 'about', label: t('About') },
        { key: 'friends', label: t('Friends') },
        { key: 'photos', label: t('Photos') },
        { key: 'videos', label: t('Videos') },
    ];
    const tabHref = (key: Props['tab']) => route('people.show', { user: profile.id, ...(key === 'posts' ? {} : { tab: key }) });

    return (
        <ClubLayout>
            <Head title={profile.name} />
            <div className="mx-auto w-full max-w-[1100px] space-y-4">
                <section className="overflow-hidden rounded-b-lg bg-white shadow-sm dark:bg-neutral-900">
                    <div className="relative">
                        <div
                            className="from-brand via-header-from to-header-to aspect-[1640/624] max-h-[400px] w-full rounded-b-lg bg-gradient-to-br bg-cover bg-center"
                            style={profile.cover ? { backgroundImage: `url(${profile.cover})` } : undefined}
                        />
                        {profile.is_me && (
                            <PictureButton
                                route="people.cover"
                                hasPicture={Boolean(profile.cover)}
                                className="absolute end-4 bottom-4 inline-flex items-center gap-2 rounded-md bg-white/90 px-3 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-white"
                            >
                                <Camera className="size-4" /> <span className="hidden sm:inline">{t('Edit cover photo')}</span>
                            </PictureButton>
                        )}
                    </div>

                    <div className="flex flex-col items-center gap-3 px-4 pb-4 sm:flex-row sm:items-end sm:gap-5 sm:px-8">
                        <div className="relative -mt-16 shrink-0 sm:-mt-10">
                            <UserAvatar
                                person={profile}
                                className="size-36 ring-4 ring-white sm:size-44 dark:ring-neutral-900"
                                fallbackClassName="text-4xl"
                            />
                            {profile.is_me && (
                                <PictureButton
                                    route="people.avatar"
                                    hasPicture={Boolean(profile.avatar)}
                                    className="absolute end-1 bottom-2 flex size-9 items-center justify-center rounded-full bg-slate-200 text-slate-900 ring-2 ring-white hover:bg-slate-300 dark:ring-neutral-900"
                                >
                                    <Camera className="size-5" />
                                </PictureButton>
                            )}
                        </div>
                        <div className="min-w-0 flex-1 text-center sm:pb-2 sm:text-start">
                            <h1 dir="auto" className="text-2xl font-bold sm:text-3xl">
                                {profile.name}
                            </h1>
                            {profile.friends_count !== null && (
                                <Link href={tabHref('friends')} className="text-muted-foreground text-[15px] font-semibold hover:underline">
                                    {t(':count friends', { count: profile.friends_count })}
                                </Link>
                            )}
                            {friends.length > 0 && (
                                <div className="mt-1.5 flex justify-center -space-x-2 sm:justify-start rtl:space-x-reverse">
                                    {friends.slice(0, 8).map((friend) => (
                                        <Link key={friend.id} href={route('people.show', friend.id)} title={friend.name}>
                                            <UserAvatar
                                                person={friend}
                                                className="size-8 ring-2 ring-white dark:ring-neutral-900"
                                                fallbackClassName="text-xs"
                                            />
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap justify-center gap-2 sm:pb-2">
                            {profile.is_me ? (
                                <button type="button" className={secondary} onClick={() => setEditing(true)}>
                                    <Pencil className="size-4" /> {t('Edit profile')}
                                </button>
                            ) : profile.blocked_by_me ? (
                                <button type="button" className={secondary} onClick={() => router.delete(route('blocks.user.destroy', profile.id))}>
                                    <Ban className="size-4" /> {t('Unblock')}
                                </button>
                            ) : (
                                <>
                                    <FriendButton profile={profile} />
                                    {profile.can_message && (
                                        <button
                                            type="button"
                                            className={profile.friendship === 'friends' ? primary : secondary}
                                            onClick={() => router.post(route('messages.start', profile.id))}
                                        >
                                            <MessageCircle className="size-4" /> {t('Message')}
                                        </button>
                                    )}
                                    <DropdownMenu>
                                        <DropdownMenuTrigger className={secondary} aria-label={t('More')}>
                                            <Ellipsis className="size-4" />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    window.confirm(
                                                        t('Block :name? You will stop seeing each other in the club.', { name: profile.name }),
                                                    ) && router.post(route('blocks.user', profile.id))
                                                }
                                            >
                                                <Ban className="size-4" /> {t('Block')}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    profile.messages_blocked_by_me
                                                        ? router.delete(route('blocks.messages.destroy', profile.id), { preserveScroll: true })
                                                        : window.confirm(
                                                              t('Block messages from :name? Everything else stays as it is.', { name: profile.name }),
                                                          ) && router.post(route('blocks.messages.store', profile.id), {}, { preserveScroll: true })
                                                }
                                            >
                                                <MessageCircleOff className="size-4" />{' '}
                                                {profile.messages_blocked_by_me ? t('Unblock messages') : t('Block messages only')}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </>
                            )}
                        </div>
                    </div>

                    <nav
                        data-blocked={profile.blocked_by_me}
                        className="mx-4 flex gap-1 overflow-x-auto border-t border-slate-200 data-[blocked=true]:hidden sm:mx-8 dark:border-neutral-800"
                    >
                        {tabs.map((item) => (
                            <Link
                                key={item.key}
                                href={tabHref(item.key)}
                                preserveScroll
                                className={`relative shrink-0 rounded-md px-4 py-3.5 text-[15px] font-semibold ${tab === item.key ? 'text-brand' : 'text-muted-foreground hover:bg-slate-100 dark:hover:bg-neutral-800'}`}
                            >
                                {item.label}
                                {tab === item.key && <span className="bg-brand absolute inset-x-1 bottom-0 h-[3px] rounded-full" />}
                            </Link>
                        ))}
                    </nav>
                </section>

                {profile.blocked_by_me && (
                    <section className={`${card} text-muted-foreground text-center text-[15px]`}>
                        {t('You blocked :name. You do not see each other’s posts, comments or messages.', { name: profile.name })}
                    </section>
                )}

                {tab === 'posts' && !profile.blocked_by_me && (
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                        <div className="space-y-4 lg:sticky lg:top-[76px] lg:self-start">
                            <section className={card}>
                                <h2 className="mb-3 text-xl font-bold">{t('Intro')}</h2>
                                <Details profile={profile} locale={locale} short />
                                {profile.is_me && (
                                    <button type="button" className={`${secondary} mt-3 w-full`} onClick={() => setEditing(true)}>
                                        {t('Edit details')}
                                    </button>
                                )}
                            </section>
                            {photos.length > 0 && (
                                <section className={card}>
                                    <Heading title={t('Photos')} href={tabHref('photos')} />
                                    <PhotoGrid photos={photos} className="grid-cols-3 overflow-hidden rounded-lg" />
                                </section>
                            )}
                            {!profile.friends_hidden && (
                                <section className={card}>
                                    <Heading title={t('Friends')} href={tabHref('friends')} />
                                    <p className="text-muted-foreground -mt-2 mb-3 text-[15px]">
                                        {t(':count friends', { count: profile.friends_count ?? 0 })}
                                    </p>
                                    <FriendGrid friends={friends} small />
                                </section>
                            )}
                        </div>
                        <div className="min-w-0 space-y-4">
                            {profile.is_me && <PostComposer />}
                            {posts && <PostList posts={posts} />}
                        </div>
                    </div>
                )}

                {tab === 'about' && !profile.blocked_by_me && (
                    <section className={card}>
                        <h2 className="mb-4 text-xl font-bold">{t('About')}</h2>
                        <Details profile={profile} locale={locale} />
                        {profile.is_me && (
                            <button type="button" className={`${secondary} mt-4`} onClick={() => setEditing(true)}>
                                <Pencil className="size-4" /> {t('Edit details')}
                            </button>
                        )}
                    </section>
                )}

                {tab === 'friends' && !profile.blocked_by_me && (
                    <section className={card}>
                        <h2 className="mb-4 text-xl font-bold">{t('Friends')}</h2>
                        {profile.friends_hidden ? (
                            <p className="text-muted-foreground text-[15px]">{t('The friends list is hidden.')}</p>
                        ) : (
                            <FriendGrid friends={friends} />
                        )}
                    </section>
                )}

                {tab === 'photos' && !profile.blocked_by_me && (
                    <section className={card}>
                        <div className="mb-4 flex items-center justify-between gap-2">
                            <h2 className="text-xl font-bold">{t('Albums')}</h2>
                            {profile.is_me && (
                                <button type="button" className={secondary} onClick={() => setCreatingAlbum(true)}>
                                    <Plus className="size-4" /> {t('New album')}
                                </button>
                            )}
                        </div>
                        {albums.length === 0 ? (
                            <p className="text-muted-foreground text-[15px]">{t('No albums yet.')}</p>
                        ) : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                {albums.map((album) => (
                                    <Link key={album.id} href={route('albums.show', album.id)} className="group min-w-0">
                                        <div className="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-neutral-800">
                                            {album.cover ? (
                                                <img
                                                    src={album.cover}
                                                    alt=""
                                                    loading="lazy"
                                                    className="size-full object-cover transition group-hover:scale-105"
                                                />
                                            ) : (
                                                <Images className="text-muted-foreground size-10" />
                                            )}
                                        </div>
                                        <div dir="auto" className="mt-1 truncate text-[15px] font-semibold">
                                            {albumName(album, t)}
                                        </div>
                                        <div className="text-muted-foreground text-[13px]">{t(':count photos', { count: album.photos_count })}</div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                )}
                {tab === 'photos' && photos.length > 0 && (
                    <section className={card}>
                        <h2 className="mb-4 text-xl font-bold">{t('All photos')}</h2>
                        <PhotoGrid photos={photos} className="grid-cols-3 sm:grid-cols-5" />
                    </section>
                )}

                {tab === 'videos' && !profile.blocked_by_me && (
                    <section className={card}>
                        <div className="mb-4 flex items-center justify-between gap-2">
                            <h2 className="text-xl font-bold">{t('Videos')}</h2>
                            {profile.is_me && (
                                <Link href={route('videos.index', { add: 1 })} className={secondary}>
                                    <Plus className="size-4" /> {t('Add video')}
                                </Link>
                            )}
                        </div>
                        {videos.length === 0 ? (
                            <p className="text-muted-foreground text-[15px]">{t('No videos yet.')}</p>
                        ) : (
                            <VideoGrid videos={videos} />
                        )}
                    </section>
                )}
            </div>

            {profile.is_me && <EditProfile profile={profile} open={editing} onClose={() => setEditing(false)} />}
            {profile.is_me && <AlbumForm open={creatingAlbum} onClose={() => setCreatingAlbum(false)} />}
        </ClubLayout>
    );
}

function Heading({ title, href }: { title: string; href: string }) {
    const t = useT();

    return (
        <div className="mb-3 flex items-center justify-between">
            <h2 className="text-xl font-bold">{title}</h2>
            <Link href={href} className="text-brand rounded-md px-2 py-1 text-[15px] hover:bg-slate-100 dark:hover:bg-neutral-800">
                {t('See all')}
            </Link>
        </div>
    );
}

function Details({ profile, locale, short = false }: { profile: Profile; locale: string; short?: boolean }) {
    const t = useT();
    const rows: [ReactNode, ReactNode][] = [];
    const date = (iso: string, options: Intl.DateTimeFormatOptions) => new Date(iso).toLocaleDateString(locale, options);

    if (profile.location)
        rows.push([
            <MapPin key="i" />,
            <span dir="auto" title={profile.location_translated ? profile.location : undefined}>
                {profile.location_translated ?? profile.location}
            </span>,
        ]);
    if (profile.country) rows.push([<Flag key="i" />, `${countryFlag(profile.country)} ${countryName(profile.country, locale)}`]);
    if (profile.website)
        rows.push([
            <Globe key="i" />,
            <a href={profile.website} target="_blank" rel="noreferrer nofollow" className="text-brand break-all hover:underline">
                {profile.website.replace(/^https?:\/\//, '')}
            </a>,
        ]);
    if (profile.birthdate)
        rows.push([<Cake key="i" />, date(profile.birthdate, { day: 'numeric', month: 'long', ...(short ? {} : { year: 'numeric' }) })]);
    if (profile.joined_at)
        rows.push([<Clock key="i" />, t('Joined :date', { date: date(profile.joined_at, { day: 'numeric', month: 'long', year: 'numeric' }) })]);
    if (!short && profile.last_seen_at) rows.push([<Check key="i" />, t('Last seen :time', { time: relativeTime(profile.last_seen_at, locale) })]);

    return (
        <div className="space-y-3 text-[15px]">
            {profile.status && (
                <p dir="auto" className="text-center font-medium">
                    {profile.status}
                </p>
            )}
            {profile.about && (
                <p dir="auto" className={`whitespace-pre-line ${short ? 'line-clamp-6 text-center' : ''}`}>
                    {profile.about}
                </p>
            )}
            {rows.map(([icon, text], i) => (
                <div key={i} className="flex items-center gap-3">
                    <span className="text-muted-foreground shrink-0 [&>svg]:size-5">{icon}</span>
                    <span dir="auto" className="min-w-0">
                        {text}
                    </span>
                </div>
            ))}
        </div>
    );
}

function FriendGrid({ friends, small = false }: { friends: Person[]; small?: boolean }) {
    const t = useT();
    if (friends.length === 0) return <p className="text-muted-foreground text-[15px]">{t('No friends yet.')}</p>;

    return (
        <div className={`grid gap-3 ${small ? 'grid-cols-3' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4'}`}>
            {friends.map((friend) =>
                small ? (
                    <Link key={friend.id} href={route('people.show', friend.id)} className="min-w-0 space-y-1">
                        <FriendPicture friend={friend} />
                        <div dir="auto" className="truncate text-[13px] font-semibold">
                            {friend.name}
                        </div>
                    </Link>
                ) : (
                    <Link
                        key={friend.id}
                        href={route('people.show', friend.id)}
                        className="flex min-w-0 items-center gap-3 rounded-lg border border-slate-200 p-2 hover:bg-slate-50 dark:border-neutral-800 dark:hover:bg-neutral-800"
                    >
                        <UserAvatar person={friend} className="size-16 rounded-lg" fallbackClassName="rounded-lg" />
                        <span dir="auto" className="truncate text-[15px] font-semibold">
                            {friend.name}
                        </span>
                    </Link>
                ),
            )}
        </div>
    );
}

function FriendPicture({ friend }: { friend: Person }) {
    return friend.avatar ? (
        <img src={friend.avatar} alt="" loading="lazy" className="aspect-square w-full rounded-lg object-cover" />
    ) : (
        <UserAvatar person={friend} className="aspect-square size-auto w-full rounded-lg" fallbackClassName="rounded-lg text-2xl" />
    );
}

function PhotoGrid({ photos, className }: { photos: Photo[]; className: string }) {
    const [open, setOpen] = useState<number | null>(null);

    return (
        <div className={`grid gap-1 ${className}`}>
            <PhotoViewer photos={photos} index={open} onClose={() => setOpen(null)} />
            {photos.map((photo, i) => (
                <button key={photo.id} type="button" onClick={() => setOpen(i)} className="block overflow-hidden">
                    <img
                        src={photo.thumb}
                        alt=""
                        loading="lazy"
                        onError={(e) => ((e.currentTarget.parentElement as HTMLElement).style.display = 'none')}
                        className="aspect-square w-full object-cover transition hover:opacity-90"
                    />
                </button>
            ))}
        </div>
    );
}

/** Add friend → Request sent → Friends, with accept / decline when they asked first. */
function FriendButton({ profile }: { profile: Profile }) {
    const t = useT();
    const add = () => router.post(route('friends.store', profile.id), {}, { preserveScroll: true });
    const remove = () => router.delete(route('friends.destroy', profile.id), { preserveScroll: true });

    switch (profile.friendship) {
        case 'friends':
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger className={secondary}>
                        <UserCheck className="size-4" /> {t('Friends')}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem onSelect={() => window.confirm(t('Remove :name from friends?', { name: profile.name })) && remove()}>
                            <UserX className="size-4" /> {t('Unfriend')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        case 'sent':
            return (
                <button type="button" className={secondary} onClick={remove}>
                    <UserX className="size-4" /> {t('Cancel request')}
                </button>
            );
        case 'received':
            return (
                <>
                    <button type="button" className={primary} onClick={add}>
                        <UserCheck className="size-4" /> {t('Confirm request')}
                    </button>
                    <button type="button" className={secondary} onClick={remove}>
                        {t('Delete request')}
                    </button>
                </>
            );
        default:
            return (
                <button type="button" className={primary} onClick={add}>
                    <UserPlus className="size-4" /> {t('Add friend')}
                </button>
            );
    }
}

/** Picks a picture and uploads it right away; a menu offers removing the current one. */
function PictureButton({
    route: name,
    hasPicture,
    className,
    children,
}: {
    route: string;
    hasPicture: boolean;
    className: string;
    children: ReactNode;
}) {
    const t = useT();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = (file: File | undefined) => {
        if (!file) return;
        router.post(
            route(name),
            { image: file },
            { forceFormData: true, preserveScroll: true, onStart: () => setUploading(true), onFinish: () => setUploading(false) },
        );
    };

    return (
        <>
            <input ref={input} type="file" accept="image/*" className="hidden" onChange={(e) => upload(e.target.files?.[0])} />
            {hasPicture ? (
                <DropdownMenu>
                    <DropdownMenuTrigger className={`${className} ${uploading ? 'animate-pulse' : ''}`} aria-label={t('Change picture')}>
                        {children}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => input.current?.click()}>{t('Upload new picture')}</DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => router.post(route(name), { remove: true }, { preserveScroll: true })}>
                            {t('Remove picture')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <button
                    type="button"
                    className={`${className} ${uploading ? 'animate-pulse' : ''}`}
                    onClick={() => input.current?.click()}
                    aria-label={t('Change picture')}
                >
                    {children}
                </button>
            )}
        </>
    );
}

function EditProfile({ profile, open, onClose }: { profile: Profile; open: boolean; onClose: () => void }) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const form = useForm({
        name: profile.name,
        status: profile.status ?? '',
        about: profile.about ?? '',
        location: profile.location ?? '',
        country: profile.country ?? '',
        website: profile.website ?? '',
        birthdate: profile.birthdate ?? '',
        gender: profile.gender ?? '',
        privacy: (profile.privacy ?? {
            location: 'public',
            country: 'public',
            website: 'public',
            birthdate: 'public',
            gender: 'public',
            friends: 'public',
        }) as Record<PrivateField, Visibility>,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])) as unknown as typeof data);
        form.patch(route('people.update'), { preserveScroll: true, onSuccess: onClose });
    };

    const field =
        'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[15px] outline-none focus:border-brand dark:border-neutral-700 dark:bg-neutral-900';
    type TextKey = Exclude<keyof typeof form.data, 'privacy'>;
    /** Who sees one detail: everyone, friends, or only the owner. */
    const seen = (key: PrivateField) => (
        <VisibilitySelect value={form.data.privacy[key]} onChange={(value) => form.setData('privacy', { ...form.data.privacy, [key]: value })} />
    );
    const row = (label: string, key: TextKey, input: ReactNode, privacy?: PrivateField) => (
        <div className="block space-y-1">
            <label className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium">{label}</span>
                {privacy && seen(privacy)}
            </label>
            {input}
            {form.errors[key] && <span className="block text-sm text-red-600">{form.errors[key]}</span>}
        </div>
    );
    const text = (key: TextKey, type = 'text') => (
        <input dir="auto" type={type} className={field} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
    );

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Edit profile')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-3">
                    {row(t('Name'), 'name', text('name'))}
                    {row(t('Status'), 'status', text('status'))}
                    {row(
                        t('About me'),
                        'about',
                        <textarea
                            dir="auto"
                            rows={4}
                            className={field}
                            value={form.data.about}
                            onChange={(e) => form.setData('about', e.target.value)}
                        />,
                    )}
                    {row(t('City'), 'location', text('location'), 'location')}
                    {row(
                        t('Country'),
                        'country',
                        <CountryPicker
                            className={field}
                            locale={locale}
                            value={form.data.country}
                            onChange={(code) => form.setData('country', code)}
                        />,
                        'country',
                    )}
                    {row(t('Website'), 'website', text('website', 'url'), 'website')}
                    <div className="grid gap-3 sm:grid-cols-2">
                        {row(t('Birthday'), 'birthdate', text('birthdate', 'date'), 'birthdate')}
                        {row(
                            t('Gender'),
                            'gender',
                            <select className={field} value={form.data.gender} onChange={(e) => form.setData('gender', e.target.value)}>
                                <option value="">{t('Not specified')}</option>
                                <option value="female">{t('Female')}</option>
                                <option value="male">{t('Male')}</option>
                            </select>,
                            'gender',
                        )}
                    </div>
                    <div className="flex items-center justify-between gap-2 rounded-md bg-slate-50 px-3 py-2 dark:bg-neutral-800/60">
                        <span className="text-sm font-medium">{t('Who sees my friends list')}</span>
                        {seen('friends')}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" className={secondary} onClick={onClose}>
                            {t('Cancel')}
                        </button>
                        <button className={primary} disabled={form.processing}>
                            {t('Save')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** A small "who sees this" picker next to a profile detail. */
function VisibilitySelect({ value, onChange }: { value: Visibility; onChange: (value: Visibility) => void }) {
    const t = useT();
    const labels = useAudienceLabels();
    return (
        <span className="text-muted-foreground relative inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold dark:bg-neutral-800">
            <AudienceIcon audience={value} />
            <select
                aria-label={t('Who can see it')}
                className="cursor-pointer appearance-none bg-transparent pe-3 outline-none"
                value={value}
                onChange={(e) => onChange(e.target.value as Visibility)}
            >
                {(['public', 'friends', 'only_me'] as const).map((v) => (
                    <option key={v} value={v}>
                        {labels[v].title}
                    </option>
                ))}
            </select>
            <ChevronDown className="pointer-events-none absolute end-1 size-3" />
        </span>
    );
}
