import { usePage } from '@inertiajs/react';
import { bp } from '@/lib/base-path';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
                <img
                    src={bp('/image/manage-server-logo.png')}
                    alt={name}
                    className="h-full w-full object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
