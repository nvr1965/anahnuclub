/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

export default function Heading({ title, description }: { title: string; description?: string }) {
    return (
        <>
            <div className="mb-8 space-y-0.5">
                <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
                {description && <p className="text-muted-foreground text-sm">{description}</p>}
            </div>
        </>
    );
}
