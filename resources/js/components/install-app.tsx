import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { installPlatform, isInstalled, onInstallChange, onInstallHelp } from '@/lib/install-app';
import { Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Hidden once the club is opened as the installed app; otherwise kept up to date with the install prompt. */
export function useInstallOffered() {
    const [, refresh] = useState(0);
    useEffect(() => onInstallChange(() => refresh((n) => n + 1)), []);

    return typeof window !== 'undefined' && !isInstalled();
}

/**
 * Steps for putting the club on the home screen when the browser gives no install prompt of its own:
 * iPhone and iPad never do, and Chrome stops offering it for a while after the member said no.
 */
export function InstallAppHelp() {
    const t = useT();
    const [open, setOpen] = useState(false);
    useEffect(() => onInstallHelp(() => setOpen(true)), []);

    const platform = open ? installPlatform() : 'desktop';
    const steps =
        platform === 'ios'
            ? [t('Open the club in Safari.'), t('Tap Share at the bottom of the screen.'), t('Choose “Add to Home Screen”, then “Add”.')]
            : platform === 'android'
              ? [
                    t('Open the browser menu ⋮.'),
                    t('Choose “Install app” or “Add to Home screen”.'),
                    t('Confirm: the club icon appears on the home screen.'),
                ]
              : [
                    t('Chrome or Edge: click the install icon at the end of the address bar, or open the browser menu and choose “Install”.'),
                    t('Safari on Mac: File menu, then “Add to Dock”.'),
                    t('Firefox cannot install sites as apps: open the club in Chrome or Edge.'),
                ];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Smartphone className="text-brand size-5" />
                        {t('Install the app')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('The club will open from its own icon, in a window of its own, and can send notifications.')}
                    </DialogDescription>
                </DialogHeader>
                <ol className="list-decimal space-y-2 ps-5 text-[0.9375rem]">
                    {steps.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
            </DialogContent>
        </Dialog>
    );
}
