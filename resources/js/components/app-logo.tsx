import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#fbf8f2] p-1">
                <AppLogoIcon className="size-full object-contain" />
            </span>
            <div className="ml-1 grid flex-1 text-left">
                <span className="mb-0.5 truncate font-serif text-[23px] leading-tight font-bold">
                    {name}
                </span>
            </div>
        </>
    );
}
