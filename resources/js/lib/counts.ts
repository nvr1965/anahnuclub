/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import { onFreshNews } from '@/lib/push';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';

type Counts = NonNullable<SharedData['counts']>;

/** How often an open tab asks for new badge numbers. */
const POLL_MS = 30000;

/**
 * The top bar's badges (messages, friend requests, notifications), kept fresh while the tab is open.
 *
 * One tab asks once per round however many bars show them (the top bar and the side rail are both on the
 * page, one hidden by the screen width), and it asks /counts, which counts just these three numbers:
 * before, every round built the whole page again (the feed with its posts) only to read the badges.
 */
let live: Counts | null = null;
let fetchedAt = 0;
const listeners = new Set<() => void>();
let stopPolling: (() => void) | null = null;

const noSubscription = () => () => {};

const emit = () => listeners.forEach((listener) => listener());

/** Asks the server for the numbers now, e.g. right after a chat was read. */
export async function refreshCounts() {
    try {
        const response = await fetch(route('counts'), { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        live = (await response.json()) as Counts;
        fetchedAt = Date.now();
        emit();
    } catch {
        // Tried again on the next round.
    }
}

function startPolling() {
    const timer = setInterval(() => document.visibilityState === 'visible' && refreshCounts(), POLL_MS);
    // A push, the tab back in front or the connection back: the badges at once (unless just asked).
    const stop = onFreshNews(() => Date.now() - fetchedAt > 2000 && refreshCounts());
    return () => {
        clearInterval(timer);
        stop();
    };
}

function subscribe(listener: () => void) {
    listeners.add(listener);
    if (!stopPolling) stopPolling = startPolling();
    return () => {
        listeners.delete(listener);
        if (listeners.size === 0 && stopPolling) {
            stopPolling();
            stopPolling = null;
        }
    };
}

/** The badge numbers: the latest of what the page brought and what the refresh fetched. */
export function useCounts(): SharedData['counts'] {
    const { auth, counts } = usePage<SharedData>().props;

    // A new page brings fresh numbers of its own: they replace the fetched ones.
    useEffect(() => {
        if (counts) {
            live = counts;
            fetchedAt = Date.now();
            emit();
        }
    }, [counts]);

    const current = useSyncExternalStore(
        auth.user ? subscribe : noSubscription,
        () => live,
        () => null,
    );

    return auth.user ? (current ?? counts) : null;
}
