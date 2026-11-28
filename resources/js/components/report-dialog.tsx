/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';

export const REPORT_REASONS = ['spam', 'abuse', 'hate', 'violence', 'illegal', 'copyright', 'privacy', 'other'] as const;
type Reason = (typeof REPORT_REASONS)[number];

/** "Report": tells the team what is wrong with a post or a comment; guests leave an email for the answer. */
export function ReportDialog({ open, onClose, type, id }: { open: boolean; onClose: () => void; type: 'post' | 'comment'; id: number }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const [reason, setReason] = useState<Reason | null>(null);
    const [details, setDetails] = useState('');
    const [email, setEmail] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [sent, setSent] = useState(false);

    useEffect(() => {
        if (open) {
            setReason(null);
            setDetails('');
            setErrors({});
            setSent(false);
        }
    }, [open]);

    const labels: Record<Reason, string> = {
        spam: t('Spam or advertising'),
        abuse: t('Insults, harassment or threats'),
        hate: t('Hatred against a group of people'),
        violence: t('Violence or terrorism'),
        illegal: t('Something illegal'),
        copyright: t('It infringes my copyright'),
        privacy: t('My personal data or photo without consent'),
        other: t('Something else'),
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!reason) return;
        setSending(true);
        router.post(
            route('reports.store'),
            { type, id, reason, details, email },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setSent(true),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSending(false),
            },
        );
    };

    const input = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[0.9375rem] dark:border-neutral-700 dark:bg-neutral-800';

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-md">
                <div className="border-b border-slate-200 px-4 py-3 dark:border-neutral-800">
                    <DialogTitle className="text-center text-lg">{type === 'post' ? t('Report post') : t('Report comment')}</DialogTitle>
                </div>
                {sent ? (
                    <div className="space-y-4 p-4">
                        <DialogDescription className="text-foreground text-[0.9375rem]">
                            {t('Thank you. The team will look at it and remove it if it breaks the law or the rules.')}
                        </DialogDescription>
                        <button type="button" onClick={onClose} className="bg-brand w-full rounded-md px-4 py-2 font-semibold text-white">
                            {t('Close')}
                        </button>
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-3 p-4">
                        <DialogDescription className="text-muted-foreground text-sm">{t('What is wrong with it?')}</DialogDescription>
                        <fieldset className="space-y-1">
                            <legend className="sr-only">{t('What is wrong with it?')}</legend>
                            {REPORT_REASONS.map((r) => (
                                <label
                                    key={r}
                                    className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-neutral-800"
                                >
                                    <input
                                        type="radio"
                                        name="reason"
                                        value={r}
                                        checked={reason === r}
                                        onChange={() => setReason(r)}
                                        className="size-4"
                                    />
                                    <span className="text-[0.9375rem]">{labels[r]}</span>
                                </label>
                            ))}
                        </fieldset>
                        {errors.reason && <p className="text-sm text-red-600">{errors.reason}</p>}
                        <label className="block">
                            <span className="text-muted-foreground mb-1 block text-sm">
                                {reason === 'other' ? t('Tell us what is wrong') : t('Details (optional)')}
                            </span>
                            <textarea
                                dir="auto"
                                rows={3}
                                maxLength={2000}
                                value={details}
                                onChange={(e) => setDetails(e.target.value)}
                                className={input}
                            />
                        </label>
                        {errors.details && <p className="text-sm text-red-600">{errors.details}</p>}
                        {!auth.user && (
                            <label className="block">
                                <span className="text-muted-foreground mb-1 block text-sm">{t('Your email, for our answer')}</span>
                                <input type="email" dir="ltr" required value={email} onChange={(e) => setEmail(e.target.value)} className={input} />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                            </label>
                        )}
                        <button
                            type="submit"
                            disabled={!reason || sending || (reason === 'other' && details.trim() === '')}
                            className="bg-brand w-full rounded-md px-4 py-2 font-semibold text-white disabled:opacity-50"
                        >
                            {t('Send')}
                        </button>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
