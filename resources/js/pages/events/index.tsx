import { EventForm } from '@/components/event-form';
import { EventCard } from '@/components/post-media';
import AppLayout from '@/layouts/app-layout';
import { useT } from '@/lib/i18n';
import { type EventItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    upcoming: EventItem[];
    past: EventItem[];
    groups: { id: number; name: string }[];
}

export default function Events({ upcoming, past, groups }: Props) {
    const t = useT();
    const [creating, setCreating] = useState(false);
    const [showPast, setShowPast] = useState(false);

    return (
        <AppLayout>
            <Head title={t('Events')} />
            <div className="space-y-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-2xl font-bold">{t('Events')}</h1>
                    <button
                        type="button"
                        onClick={() => setCreating(true)}
                        className="bg-brand hover:bg-brand-dark inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white"
                    >
                        <CalendarPlus className="size-4" /> {t('Create event')}
                    </button>
                </div>

                <h2 className="text-lg font-semibold">{t('Upcoming events')}</h2>
                {upcoming.length === 0 ? (
                    <p className="text-muted-foreground rounded-lg bg-white p-6 text-center text-[15px] shadow-sm dark:bg-neutral-900">
                        {t('No upcoming events. Create the first one!')}
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {upcoming.map((event) => (
                            <div key={event.id} className="overflow-hidden rounded-lg shadow-sm">
                                <EventCard event={event} />
                            </div>
                        ))}
                    </div>
                )}

                {past.length > 0 && (
                    <>
                        <button type="button" onClick={() => setShowPast(!showPast)} className="text-brand text-[15px] font-semibold hover:underline">
                            {showPast ? t('Hide past events') : t('Past events (:count)', { count: past.length })}
                        </button>
                        {showPast && (
                            <div className="grid gap-4 opacity-80 sm:grid-cols-2">
                                {past.map((event) => (
                                    <div key={event.id} className="overflow-hidden rounded-lg shadow-sm">
                                        <EventCard event={event} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
            <EventForm groups={groups} open={creating} onClose={() => setCreating(false)} />
        </AppLayout>
    );
}
