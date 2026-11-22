import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-border px-4 md:px-6">
            <SidebarTrigger className="-ml-1 size-9 rounded-control text-foreground hover:bg-accent" />
            <span aria-hidden="true" className="h-5 w-px bg-border" />
            <Breadcrumbs breadcrumbs={breadcrumbs} />
        </header>
    );
}
