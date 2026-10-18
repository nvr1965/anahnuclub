import { type EditableEvent, EventForm } from '@/components/event-form';
import { eventDate } from '@/components/post-media';
import { ShareMenu } from '@/components/share-menu';
import { TranslatedText } from '@/components/translated-text';
import { UserAvatar } from '@/components/user-avatar';
import { useAutoTranslate } from '@/hooks/use-auto-translate';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type EventItem, type Person, type SharedData, type TranslationInfo } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Check, MapPin, Pencil, Star, Trash2, Users } from 'lucide-react';
import { type ReactNode, useMemo, useState } from 'react';

interface Event extends EventItem, EditableEvent {
    description: string;
    author: Person | null;
    group: { id: number; name: string; slug: string } | null;
    mine: boolean;
    can_delete: boolean;
    url: string;
    translation: TranslationInfo | null;
}

interface Props {
    event: Event;
    going: Person[];
    interested: Person[];
    groups: { id: number; name: string }[];
}

const button = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-[15px] font-semibold transition';
const primary = `${button} bg-brand hover:bg-brand-dark text-white`;
const secondary = `${button} bg-slate-200 hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700`;

export default function EventShow({ event, going, interested, groups }: Props) {
    const t = useT();
    const { locale } = usePage<SharedData>().props;
    const [editing, setEditing] = useState(false);
    const items = useMemo(() => [event], [event]);
    const translated = useAutoTranslate(items, locale);
    const past = event.starts_at && new Date(event.ends_at ?? event.starts_at).getTime() < Date.now() - 6 * 3600 * 1000;

    const respond = (status: 'going' | 'interested') =>
        router.post(route('events.respond', event.id), { status: event.my_status === status ? 'none' : status }, { preserveScroll: true });
    const remove = () => window.confirm(t('Delete this event?')) && router.delete(route('events.destroy', event.id));

    return (
        <AppLayout>
            <Head title={event.title} />
            <div className="mx-auto max-w-[900px] space-y-4">
                <article className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900">
                    <div
                        className="from-brand via-header-from to-header-to aspect-[1640/624] bg-gradient-to-br bg-cover bg-center"
                        style={event.cover ? { backgroundImage: `url(${event.cover})` } : undefined}
                    />
                    <div className="space-y-4 p-5">
                        <div className="text-[15px] font-semibold text-red-600 uppercase dark:text-red-400">
                            {eventDate(event.starts_at, locale)}
                            {event.ends_at && <> – {eventDate(event.ends_at, locale)}</>}
                        </div>
                        <TranslatedText
                            item={event}
                            fetched={translated('event', event.id)}
                            original={{ title: event.title, body: '' }}
                            titleClassName="text-2xl font-bold"
                        />
                        <div className="flex flex-wrap gap-2">
                            {past ? (
                                <span className="text-muted-foreground text-[15px]">{t('This event has ended.')}</span>
                            ) : (
                                <>
                                    <button
                                        type="button"
                                        className={event.my_status === 'going' ? primary : secondary}
                                        onClick={() => respond('going')}
                                    >
                                        <Check className="size-4" /> {t('Going')}
                                    </button>
                                    <button
                                        type="button"
                                        className={event.my_status === 'interested' ? primary : secondary}
                                        onClick={() => respond('interested')}
                                    >
                                        <Star className="size-4" /> {t('Interested')}
                                    </button>
                                </>
                            )}
                            <ShareMenu url={event.url} title={event.title} className={`${secondary} outline-none`} />
                            {event.mine && (
                                <button type="button" className={secondary} onClick={() => setEditing(true)} aria-label={t('Edit')}>
                                    <Pencil className="size-4" />
                                </button>
                            )}
                            {event.can_delete && (
                                <button type="button" className={secondary} onClick={remove} aria-label={t('Delete')}>
                                    <Trash2 className="size-4" />
                                </button>
                            )}
                        </div>
                        <div className="space-y-2.5 border-t border-slate-200 pt-4 text-[15px] dark:border-neutral-800">
                            <Fact icon={<CalendarDays />}>{eventDate(event.starts_at, locale)}</Fact>
                            {event.location && <Fact icon={<MapPin />}>{event.location}</Fact>}
                            <Fact icon={<Users />}>
                                {t(':count going', { count: going.length })} · {t(':count interested', { count: interested.length })}
                            </Fact>
                            {event.author && (
                                <Fact icon={<UserAvatar person={event.author} className="size-5" fallbackClassName="text-[9px]" />}>
                                    {t('Organised by')}{' '}
                                    <Link href={route('people.show', event.author.id)} className="font-semibold hover:underline">
                                        {event.author.name}
                                    </Link>
                                    {event.group && (
                                        <>
                                            {' · '}
                                            <Link href={route('groups.show', event.group.slug)} className="font-semibold hover:underline">
                                                {event.group.name}
                                            </Link>
                                        </>
                                    )}
                                </Fact>
                            )}
                        </div>
                        {event.description && (
                            <div className="border-t border-slate-200 pt-4 dark:border-neutral-800">
                                <TranslatedText
                                    item={event}
                                    fetched={translated('event', event.id)}
                                    original={{ body: event.description }}
                                    bodyField="description"
                                />
                            </div>
                        )}
                    </div>
                </article>

                <People title={t('Going')} people={going} />
                <People title={t('Interested')} people={interested} />
            </div>
            {event.mine && <EventForm event={event} groups={groups} open={editing} onClose={() => setEditing(false)} />}
        </AppLayout>
    );
}

function Fact({ icon, children }: { icon: ReactNode; children: ReactNode }) {
    return (
        <div className="flex items-center gap-3">
            <span className="text-muted-foreground flex size-5 shrink-0 items-center justify-center [&>svg]:size-5">{icon}</span>
            <span dir="auto" className="min-w-0">
                {children}
            </span>
        </div>
    );
}

function People({ title, people }: { title: string; people: Person[] }) {
    if (people.length === 0) return null;

    return (
        <section className="rounded-lg bg-white p-4 shadow-sm dark:bg-neutral-900">
            <h2 className="mb-3 font-bold">
                {title} <span className="text-muted-foreground font-normal">{people.length}</span>
            </h2>
            <div className="flex flex-wrap gap-3">
                {people.map((person) => (
                    <Link key={person.id} href={route('people.show', person.id)} className="flex w-20 flex-col items-center gap-1 text-center">
                        <UserAvatar person={person} className="size-14" />
                        <span dir="auto" className="line-clamp-2 text-xs font-medium">
                            {person.name}
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}
