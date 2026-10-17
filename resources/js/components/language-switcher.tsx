import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';

export function LanguageSwitcher({ className = '' }: { className?: string }) {
    const { locale, languages } = usePage<SharedData>().props;

    return (
        <label className={`inline-flex items-center gap-2 text-sm ${className}`}>
            <Languages className="size-4 opacity-70" />
            <select
                aria-label="Language"
                className="bg-transparent outline-none"
                value={locale}
                onChange={(e) => router.post(route('locale.update'), { locale: e.target.value }, { preserveScroll: true })}
            >
                {languages.map((language) => (
                    <option key={language.code} value={language.code}>
                        {language.native_name}
                    </option>
                ))}
            </select>
        </label>
    );
}
