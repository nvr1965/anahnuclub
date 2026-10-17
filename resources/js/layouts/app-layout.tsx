import { ContactsColumn } from '@/components/contacts-column';
import { MenuColumn } from '@/components/menu-column';
import ClubLayout from '@/layouts/club-layout';
import { type BreadcrumbItem, type Sidebar } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    sidebar?: Sidebar;
}

/** Signed-in pages: menu on the left; with community data, people and groups on the right. */
export default function AppLayout({ children, sidebar }: AppLayoutProps) {
    return (
        <ClubLayout left={<MenuColumn groups={sidebar?.groups} />} right={sidebar ? <ContactsColumn sidebar={sidebar} /> : undefined}>
            {children}
        </ClubLayout>
    );
}
