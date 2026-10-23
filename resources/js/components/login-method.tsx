import { useT } from '@/lib/i18n';

/** How a login in the journal was made. */
export function useLoginMethod() {
    const t = useT();
    const names: Record<string, string> = {
        password: t('Password'),
        register: t('Sign-up'),
        google: 'Google',
        facebook: 'Facebook',
    };

    return (method: string) => names[method] ?? method;
}
