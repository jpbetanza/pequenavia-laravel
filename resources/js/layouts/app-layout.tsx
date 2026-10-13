import { BannerDeInstalacao } from '@/components/banner-de-instalacao';
import AppLayoutTemplate from '@/layouts/app/app-header-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
            <BannerDeInstalacao />
        </AppLayoutTemplate>
    );
}
