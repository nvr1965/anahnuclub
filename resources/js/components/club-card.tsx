import { type ReactNode } from 'react';

export function ClubCard({ title, children, className = '' }: { title?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={`rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 dark:bg-neutral-900 dark:ring-neutral-800 ${className}`}>
            {title && <h2 className="border-b border-slate-100 px-5 py-3 text-sm font-semibold dark:border-neutral-800">{title}</h2>}
            <div className="p-5">{children}</div>
        </section>
    );
}
