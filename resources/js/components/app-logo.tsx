import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { brand } = usePage<SharedData>().props;

    return (
        <>
            {brand?.logo_url ? (
                <img src={brand.logo_url} alt={brand.name} className="size-8 shrink-0 rounded-lg object-contain" />
            ) : (
                <div className="bg-primary text-primary-foreground flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg shadow-sm">
                    <AppLogoIcon className="size-5" />
                </div>
            )}
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-bold">{brand?.name ?? 'Sistema May'}</span>
                {brand?.subtitle && <span className="text-muted-foreground truncate text-xs leading-tight">{brand.subtitle}</span>}
            </div>
        </>
    );
}
