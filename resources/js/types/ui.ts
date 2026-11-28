import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** Puts back what the action changed: a PATCH to this address with this data. */
    undo?: { url: string; data: Record<string, unknown> };
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
