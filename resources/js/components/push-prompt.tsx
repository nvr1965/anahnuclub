import { useT } from '@/lib/i18n';
import { enablePush, pushState, type PushState } from '@/lib/push';
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

    useEffect(() => {
        pushState()
            .then(setState)
            .catch(() => setState('unsupported'));
    }, []);

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
