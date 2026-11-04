import { useT } from '@/lib/i18n';
import { enablePush, pushState, testPush, type PushReport, type PushState } from '@/lib/push';
import { BellRing, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const DISMISSED = 'club-push-dismissed';

function dismissedRecently() {
    try {
        return Date.now() - Number(localStorage.getItem(DISMISSED) || 0) < 7 * 24 * 3600 * 1000;
    } catch {
        return false;
    }
}

/**
 * Offers notifications on this device above the chat list, as WhatsApp Web does: so new messages and
 * calls reach the member with the club closed. On an iPhone it explains that the club has to be on the
 * Home Screen first. "Not now" hides it for a week.
 */
export function PushPrompt() {
    const t = useT();
    const [state, setState] = useState<PushState | null>(null);
    const [busy, setBusy] = useState(false);
    const [hidden, setHidden] = useState(dismissedRecently);

    const [report, setReport] = useState<string | null>(null);

    useEffect(() => {
        pushState()
            .then(setState)
            .catch(() => setState('unsupported'));
    }, []);

    // "Check notifications" from the chats menu: a test to every device of the member, and what came of it.
    useEffect(() => {
        const describe = ({ state, devices, version, arrived, local }: PushReport) => {
            if (state === 'ios-install')
                return t(
                    'To get notifications on iPhone, add the club to the Home Screen: Share, then "Add to Home Screen", and open it from there.',
                );
            if (state === 'denied') return t('Notifications are blocked for this site. Allow them in the browser or phone settings and try again.');
            if (state !== 'on') return t('This browser cannot show notifications.');
            const taken = devices.filter((d) => d.status >= 200 && d.status < 300).length;
            const refused = devices.filter((d) => d.status < 200 || d.status >= 300).map((d) => `${d.service}: ${d.status || t('no answer')}`);
            return [
                t(
                    'Test notification sent to :count of :total devices. If it does not appear within a minute, notifications for this browser or app are turned off in the phone settings, or battery saving holds them.',
                    {
                        count: taken,
                        total: devices.length,
                    },
                ),
                refused.length ? t('Refused: :list', { list: refused.join(', ') }) : '',
                `[${version ?? t('old version')} · ${arrived ? t('reached this phone') : t('did not reach this phone')} · ${local ? t('cannot show: :error', { error: local }) : t('can show')}]`,
            ]
                .filter(Boolean)
                .join(' ');
        };
        const check = async () => {
            setReport(t('Checking…'));
            try {
                const result = await testPush();
                setState(result.state);
                setReport(describe(result));
            } catch {
                setReport(t('Something went wrong.'));
            }
        };
        window.addEventListener('club:push-check', check);
        return () => window.removeEventListener('club:push-check', check);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    if (report)
        return (
            <div className="bg-brand-soft dark:bg-brand/15 mx-3 mb-2 flex items-start gap-3 rounded-xl p-3 text-[0.875rem]">
                <BellRing className="text-brand mt-0.5 size-5 shrink-0" />
                <p className="min-w-0 flex-1">{report}</p>
                <button
                    type="button"
                    onClick={() => setReport(null)}
                    aria-label={t('Close')}
                    title={t('Close')}
                    className="text-muted-foreground shrink-0"
                >
                    <X className="size-4" />
                </button>
            </div>
        );

    if (hidden || !state || state === 'on' || state === 'unsupported' || state === 'denied') return null;

    const dismiss = () => {
        try {
            localStorage.setItem(DISMISSED, String(Date.now()));
        } catch {
            // Hidden for this visit only.
        }
        setHidden(true);
    };

    return (
        <div className="bg-brand-soft dark:bg-brand/15 mx-3 mb-2 flex items-start gap-3 rounded-xl p-3 text-[0.875rem]">
            <BellRing className="text-brand mt-0.5 size-5 shrink-0" />
            <div className="min-w-0 flex-1">
                {state === 'ios-install' ? (
                    <p>
                        {t(
                            'To get notifications on iPhone, add the club to the Home Screen: Share, then "Add to Home Screen", and open it from there.',
                        )}
                    </p>
                ) : (
                    <>
                        <p>{t('Get new messages and calls at once, even with the site closed.')}</p>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={async () => {
                                setBusy(true);
                                setState(await enablePush().catch(() => 'unsupported' as const));
                                setBusy(false);
                            }}
                            className="bg-brand mt-2 rounded-full px-4 py-1.5 font-semibold text-white disabled:opacity-60"
                        >
                            {t('Turn on notifications')}
                        </button>
                    </>
                )}
            </div>
            <button type="button" onClick={dismiss} aria-label={t('Not now')} title={t('Not now')} className="text-muted-foreground shrink-0">
                <X className="size-4" />
            </button>
        </div>
    );
}
