/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import { cn } from '@/lib/utils';
import { type LucideProps } from 'lucide-react';

interface IconProps extends Omit<LucideProps, 'ref'> {
    iconNode: React.ComponentType<LucideProps>;
}

export function Icon({ iconNode: IconComponent, className, ...props }: IconProps) {
    return <IconComponent className={cn('h-4 w-4', className)} {...props} />;
}
