import ClubLayout from '@/layouts/club-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    aside?: ReactNode;
}

export default function AppLayout({ children, aside }: AppLayoutProps) {
    return <ClubLayout aside={aside}>{children}</ClubLayout>;
}
