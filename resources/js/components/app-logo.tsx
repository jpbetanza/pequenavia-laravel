import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-9 rounded-md object-contain" />
            <div className="ml-1 grid flex-1 text-left">
                <span className="mb-0.5 truncate font-serif text-[23px] leading-tight font-bold">
                    {name}
                </span>
            </div>
        </>
    );
}
