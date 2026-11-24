import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

/**
 * The signed-in frame: a white rail on the left, and each page as a sheet laid on the desk
 * beside it, the same paper-on-desk look as the public site. On phones the sheet fills the screen.
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="min-w-0 overflow-x-clip bg-desk md:p-3 md:pl-3"
            >
                <div className="flex min-h-full flex-1 flex-col bg-background md:rounded-card md:shadow-page">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
