import { BannerDeInstalacao } from '@/components/banner-de-instalacao';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    painel,
    children,
}: {
    title?: string;
    description?: string;
    painel?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            painel={painel}
        >
            {children}
            <BannerDeInstalacao />
        </AuthLayoutTemplate>
    );
}
