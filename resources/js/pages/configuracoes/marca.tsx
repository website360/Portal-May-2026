import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhotoPicker } from '@/components/ui/photo-picker';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Marca', href: '/configuracoes/marca' },
];

interface BrandProps {
    brand: { name: string; subtitle: string | null; logo_url: string | null };
}

interface FormData {
    name: string;
    subtitle: string;
    logo: File | null;
    remove_logo: boolean;
    [key: string]: string | boolean | File | null;
}

export default function Marca({ brand }: BrandProps) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        name: brand.name,
        subtitle: brand.subtitle ?? '',
        logo: null,
        remove_logo: false,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(route('configuracoes.marca.update'), { forceFormData: true, preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Marca" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Marca</h2>
                        <p className="text-muted-foreground text-sm">O logo, o nome e o subtítulo que aparecem no menu do sistema.</p>
                    </div>

                    <Card>
                        <form onSubmit={submit} className="grid gap-5 p-4">
                            <div className="grid gap-2">
                                <Label>Logo</Label>
                                <PhotoPicker
                                    file={data.logo}
                                    existingUrl={brand.logo_url}
                                    removed={data.remove_logo}
                                    onSelect={(file) => setData((prev) => ({ ...prev, logo: file, remove_logo: false }))}
                                    onRemove={() => setData((prev) => ({ ...prev, logo: null, remove_logo: true }))}
                                    hint="PNG, JPG ou WEBP, até 2 MB. Sem logo, mostramos o ícone padrão."
                                    renderAvatar={(url) =>
                                        url ? (
                                            <span className="flex size-14 items-center justify-center overflow-hidden rounded-xl">
                                                <img src={url} alt="" className="size-full object-contain" />
                                            </span>
                                        ) : (
                                            <span className="bg-primary text-primary-foreground flex size-14 items-center justify-center rounded-xl shadow-sm">
                                                <AppLogoIcon className="size-7" />
                                            </span>
                                        )
                                    }
                                />
                                {errors.logo && <p className="text-destructive text-xs font-medium">{errors.logo}</p>}
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">
                                        Nome<span className="text-destructive ml-0.5">*</span>
                                    </Label>
                                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Sistema May" />
                                    {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="subtitle">Subtítulo</Label>
                                    <Input id="subtitle" value={data.subtitle} onChange={(e) => setData('subtitle', e.target.value)} placeholder="Agência May" />
                                    {errors.subtitle && <p className="text-destructive text-xs font-medium">{errors.subtitle}</p>}
                                </div>
                            </div>

                            <div>
                                <Button type="submit" loading={processing}>
                                    Salvar
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
