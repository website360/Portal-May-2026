import { Head } from '@inertiajs/react';

import AppearanceAccent from '@/components/appearance-accent';
import AppearanceInterface from '@/components/appearance-interface';
import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Aparência', href: '/configuracoes/aparencia' },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aparência" />

            <SettingsLayout>
                <div className="space-y-8">
                    <div className="space-y-4">
                        <HeadingSmall title="Tema" description="Claro, escuro ou o mesmo do seu sistema operacional" />
                        <AppearanceTabs />
                    </div>

                    <Separator />

                    <div className="space-y-4">
                        <HeadingSmall title="Cor de destaque" description="A cor dos botões, links e elementos ativos" />
                        <AppearanceAccent />
                    </div>

                    <Separator />

                    <div className="space-y-4">
                        <HeadingSmall title="Interface" description="Ajuste o tamanho e o arredondamento ao seu gosto" />
                        <AppearanceInterface />
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
